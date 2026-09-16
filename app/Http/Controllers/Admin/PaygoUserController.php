<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaygoVerificationIntent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaygoUserController extends Controller
{
    public function index(Request $request)
    {
        $paygoUsers = $this->filteredPaygoUsersQuery($request)
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PaygoVerificationIntent $intent) => $this->paygoUserPayload($intent));

        return Inertia::render('Admin/PaygoUsers/Index', [
            'paygoUsers' => $paygoUsers,
            'stats' => [
                'total' => $this->contactableIntentsQuery()->count(),
                'paid' => $this->contactableIntentsQuery()->whereIn('status', ['paid', 'used', 'verifying'])->count(),
                'pending' => $this->contactableIntentsQuery()->where('status', 'pending')->count(),
                'result' => $this->contactableIntentsQuery()->where('flow_type', 'result')->count(),
            ],
            'filters' => $request->only(['search', 'status', 'flow_type']),
        ]);
    }

    private function filteredPaygoUsersQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));

        return $this->contactableIntentsQuery()
            ->select([
                'id',
                'customer_paygo_service_id',
                'user_id',
                'verification_service_id',
                'verification_request_id',
                'flow_type',
                'reference',
                'lookup_label',
                'amount',
                'status',
                'buyer_phone',
                'paid_at',
                'used_at',
                'expires_at',
                'metadata',
                'created_at',
            ])
            ->with([
                'user:id,name,email',
                'user.customer:id,user_id,company_name,referral_code',
                'paygoService:id,name,public_slug,user_id,verification_service_id',
                'verificationService:id,name,slug',
                'verificationRequest:id,reference,status,search_parameter',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery->where('reference', 'like', "%{$search}%")
                        ->orWhere('lookup_label', 'like', "%{$search}%")
                        ->orWhere('buyer_phone', 'like', "%{$search}%")
                        ->orWhere('metadata->buyer_email', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('paygoService', function (Builder $serviceQuery) use ($search) {
                            $serviceQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('flow_type'), fn (Builder $query) => $query->where('flow_type', $request->string('flow_type')));
    }

    private function contactableIntentsQuery(): Builder
    {
        return PaygoVerificationIntent::query()
            ->where(function (Builder $query) {
                $query->whereNotNull('buyer_phone')
                    ->orWhereNotNull('metadata->buyer_email');
            });
    }

    private function paygoUserPayload(PaygoVerificationIntent $intent): array
    {
        $metadata = is_array($intent->metadata) ? $intent->metadata : [];

        return [
            'id' => $intent->id,
            'reference' => $intent->reference,
            'email' => $metadata['buyer_email'] ?? null,
            'phone' => $intent->buyer_phone,
            'flow_type' => $intent->flow_type,
            'status' => $intent->status,
            'lookup_label' => $intent->lookup_label,
            'amount' => (float) $intent->amount,
            'paid_at' => $intent->paid_at,
            'used_at' => $intent->used_at,
            'expires_at' => $intent->expires_at,
            'created_at' => $intent->created_at,
            'customer' => [
                'id' => $intent->user?->id,
                'name' => $intent->user?->customer?->company_name ?: $intent->user?->name,
                'email' => $intent->user?->email,
                'referral_code' => $intent->user?->customer?->referral_code,
            ],
            'paygo_service' => [
                'id' => $intent->paygoService?->id,
                'name' => $intent->paygoService?->name,
                'public_slug' => $intent->paygoService?->public_slug,
            ],
            'verification_service' => [
                'id' => $intent->verificationService?->id,
                'name' => $intent->verificationService?->name,
                'slug' => $intent->verificationService?->slug,
            ],
            'verification_request' => $intent->verificationRequest ? [
                'id' => $intent->verificationRequest->id,
                'reference' => $intent->verificationRequest->reference,
                'status' => $intent->verificationRequest->status,
                'search_parameter' => $intent->verificationRequest->search_parameter,
            ] : null,
        ];
    }
}
