<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitCustomerKycRequest;
use App\Models\Customer;
use App\Models\CustomerKycSubmission;
use App\Support\KycQuestionnaire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KycController extends Controller
{
    public function index(Request $request): Response
    {
        $customer = $request->user()->customer;
        abort_unless($customer, 404);
        $business = $customer->account_type === 'business';
        $submission = $customer->latestKycSubmission;

        return Inertia::render('Customer/Kyc/Index', [
            'status' => $customer->kycStatus(),
            'approvedAt' => $customer->kyc_approved,
            'accountType' => $customer->account_type ?? 'individual',
            'sections' => KycQuestionnaire::sections($business),
            'documentFields' => KycQuestionnaire::documents($business),
            'submission' => $submission?->reviewPayload(),
            'defaults' => [
                'registered_name' => $customer->company_name ?: $request->user()->name,
                'registration_number' => $customer->registration_number,
                'office_address' => $customer->full_address,
                'country' => $customer->country ?: 'Nigeria',
                'contact_name' => $request->user()->name,
                'contact_email' => $request->user()->email,
                'contact_phone' => $request->user()->phone,
                'website' => $customer->website,
                'operating_countries' => $customer->country ?: 'Nigeria',
            ],
        ]);
    }

    public function store(SubmitCustomerKycRequest $request): RedirectResponse
    {
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, &$storedPaths) {
                $customer = Customer::query()->whereKey($request->user()->customer->id)->lockForUpdate()->firstOrFail();
                $latest = $customer->latestKycSubmission;

                if ($customer->hasApprovedKyc() || $latest?->status() === 'pending') {
                    throw ValidationException::withMessages(['submission' => 'This KYC has already been submitted.']);
                }

                $business = $customer->account_type === 'business';
                $documents = $latest?->documents ?? [];

                foreach (KycQuestionnaire::documents($business) as $field) {
                    $file = $request->file('documents.'.$field['key']);
                    if (! $file) {
                        continue;
                    }

                    $path = $file->store('kyc/'.$customer->id, 'local');
                    if ($path === false) {
                        throw new RuntimeException('Unable to store KYC document.');
                    }

                    $storedPaths[] = $path;
                    $documents[$field['key']] = ['path' => $path, 'name' => $file->getClientOriginalName(), 'label' => $field['label']];
                }

                $customer->kycSubmissions()->create([
                    'questions' => KycQuestionnaire::sections($business),
                    'answers' => [...$request->validated('answers'), 'declaration' => true],
                    'documents' => $documents,
                    'submitted_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        return to_route('customer.kyc.index')->with('success', 'KYC submitted for admin review.');
    }

    public function document(Request $request, CustomerKycSubmission $submission, string $document): StreamedResponse
    {
        abort_unless($submission->customer_id === $request->user()->customer?->id, 403);

        return $submission->downloadDocument($document);
    }
}
