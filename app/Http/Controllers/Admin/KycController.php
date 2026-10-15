<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewCustomerKycRequest;
use App\Models\Customer;
use App\Models\CustomerKycSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->input('status'), ['pending', 'approved', 'rejected', 'all'], true) ? $request->input('status') : 'pending';
        $search = trim((string) $request->input('search', ''));
        $submissions = CustomerKycSubmission::query()
            ->select(['id', 'customer_id', 'submitted_at', 'reviewed_at', 'rejection_reason'])
            ->with('customer.user:id,name,email')
            ->when($status === 'pending', fn ($query) => $query->whereNull('reviewed_at'))
            ->when($status === 'approved', fn ($query) => $query->whereNotNull('reviewed_at')->whereNull('rejection_reason'))
            ->when($status === 'rejected', fn ($query) => $query->whereNotNull('rejection_reason'))
            ->when($search !== '', fn ($query) => $query->whereHas('customer.user', fn ($userQuery) => $userQuery
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest('submitted_at')->latest('id')->paginate(15)->withQueryString()
            ->through(fn (CustomerKycSubmission $submission) => [
                'id' => $submission->id,
                'name' => $submission->customer->user->name,
                'email' => $submission->customer->user->email,
                'company' => $submission->customer->company_name,
                'status' => $submission->status(),
                'submitted_at' => $submission->submitted_at,
            ]);

        return Inertia::render('Admin/Kyc/Index', ['submissions' => $submissions, 'filters' => compact('status', 'search')]);
    }

    public function show(CustomerKycSubmission $submission): Response
    {
        $submission->load(['customer.user', 'reviewer']);

        return Inertia::render('Admin/Kyc/Show', [
            'submission' => $submission->reviewPayload(true),
            'customer' => [
                'id' => $submission->customer->user_id,
                'name' => $submission->customer->user->name,
                'email' => $submission->customer->user->email,
                'company' => $submission->customer->company_name,
                'account_type' => $submission->customer->account_type,
            ],
        ]);
    }

    public function review(ReviewCustomerKycRequest $request, CustomerKycSubmission $submission): RedirectResponse
    {
        DB::transaction(function () use ($request, $submission) {
            $customer = Customer::query()->whereKey($submission->customer_id)->lockForUpdate()->firstOrFail();
            $lockedSubmission = CustomerKycSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if ($lockedSubmission->status() !== 'pending' || $customer->latestKycSubmission?->id !== $lockedSubmission->id || $customer->hasApprovedKyc()) {
                throw ValidationException::withMessages(['decision' => 'This submission has already been reviewed or replaced.']);
            }

            $approved = $request->validated('decision') === 'approve';
            $reviewedAt = now();
            $lockedSubmission->update([
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => $reviewedAt,
                'rejection_reason' => $approved ? null : $request->validated('rejection_reason'),
            ]);
            $customer->forceFill(['kyc_approved' => $approved ? $reviewedAt : null])->save();
        });

        return back()->with('success', $request->validated('decision') === 'approve' ? 'KYC approved. API access is now available.' : 'KYC rejected. The customer can correct and resubmit it.');
    }

    public function document(CustomerKycSubmission $submission, string $document): StreamedResponse
    {
        return $submission->downloadDocument($document);
    }
}
