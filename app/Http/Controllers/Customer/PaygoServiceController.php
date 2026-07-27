<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\PaygoWallet;
use App\Models\PaygoWalletTransaction;
use App\Models\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaygoServiceController extends Controller
{
    public function index(Request $request)
    {
        CustomerPaygoService::syncResultBoardSetForUser($request->user());

        $services = CustomerPaygoService::query()
            ->where('user_id', $request->user()->id)
            ->with(['user.customer', 'verificationService:id,name,slug,default_price'])
            ->withCount([
                'intents',
                'intents as paid_intents_count' => fn ($query) => $query->whereIn('status', ['paid', 'verifying', 'used']),
                'intents as used_intents_count' => fn ($query) => $query->where('status', 'used'),
            ])
            ->latest()
            ->get()
            ->map(fn (CustomerPaygoService $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'public_slug' => $service->public_slug,
                'price' => (float) $service->price,
                'is_active' => $service->is_active,
                'success_url' => $service->success_url,
                'failure_url' => $service->failure_url,
                'response_mode' => $service->response_mode ?? 'redirect',
                'service' => $service->verificationService,
                'service_type' => $service->isResultVerification() ? 'result' : 'identity',
                'board' => $service->isResultVerification() ? strtoupper((string) $service->resultBoard()) : null,
                'system_price' => (float) $request->user()->getPriceForService($service->verificationService),
                'initiate_url' => $service->initiateUrl(),
                'verify_url' => $service->verifyUrl(),
                'result_url' => $service->isResultVerification() ? $service->resultUrl() : null,
                'result_selector_url' => $service->isResultVerification() ? $service->resultSelectorUrl() : null,
                'intents_count' => $service->intents_count,
                'paid_intents_count' => $service->paid_intents_count,
                'used_intents_count' => $service->used_intents_count,
                'created_at' => $service->created_at,
            ]);

        $verificationServices = $this->paygoVerificationServiceOptions($request);

        $paygoWallet = PaygoWallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'pending_withdrawal' => 0, 'currency' => 'NGN', 'is_active' => true],
        );

        return Inertia::render('Customer/Paygo/Index', [
            'paygoServices' => $services,
            'verificationServices' => $verificationServices,
            'paygoWallet' => [
                'balance' => (float) $paygoWallet->balance,
                'pending_withdrawal' => (float) $paygoWallet->pending_withdrawal,
                'currency' => $paygoWallet->currency,
            ],
            'withdrawalRequests' => $paygoWallet->withdrawalRequests()
                ->latest()
                ->limit(10)
                ->get(['reference', 'amount', 'bank_name', 'account_number', 'account_name', 'status', 'requested_at']),
        ]);
    }

    public function analytics(Request $request)
    {
        $user = $request->user();
        $wallet = PaygoWallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'pending_withdrawal' => 0, 'currency' => 'NGN', 'is_active' => true],
        );

        $intents = PaygoVerificationIntent::query()->where('user_id', $user->id);
        $paidStatuses = ['paid', 'verifying', 'used'];

        $statusCounts = (clone $intents)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $paidAmount = (float) (clone $intents)->whereIn('status', $paidStatuses)->sum('amount');
        $systemCost = (float) (clone $intents)->whereIn('status', $paidStatuses)->sum('system_price_snapshot');
        $walletCredits = (float) PaygoWalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->sum('amount');
        $withdrawals = (float) PaygoWalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'debit')
            ->where('category', 'withdrawal')
            ->sum('amount');

        $daily = (clone $intents)
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as intents, SUM(CASE WHEN status IN ("paid", "verifying", "used") THEN amount ELSE 0 END) as revenue, SUM(CASE WHEN status IN ("paid", "verifying", "used") THEN amount - system_price_snapshot ELSE 0 END) as earnings')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'intents' => (int) $row->intents,
                'revenue' => (float) $row->revenue,
                'earnings' => (float) $row->earnings,
            ]);

        $servicePerformance = CustomerPaygoService::query()
            ->where('user_id', $user->id)
            ->with('verificationService:id,name,slug')
            ->withCount([
                'intents',
                'intents as paid_count' => fn ($query) => $query->whereIn('status', $paidStatuses),
                'intents as used_count' => fn ($query) => $query->where('status', 'used'),
            ])
            ->latest()
            ->get()
            ->map(fn (CustomerPaygoService $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'service_name' => $service->verificationService?->name,
                'is_active' => $service->is_active,
                'price' => (float) $service->price,
                'system_price' => (float) $user->getPriceForService($service->verificationService),
                'margin' => max(0, (float) $service->price - (float) $user->getPriceForService($service->verificationService)),
                'intents_count' => $service->intents_count,
                'paid_count' => $service->paid_count,
                'used_count' => $service->used_count,
            ]);

        return Inertia::render('Customer/Paygo/Analytics', [
            'analytics' => [
                'wallet_balance' => (float) $wallet->balance,
                'pending_withdrawal' => (float) $wallet->pending_withdrawal,
                'services_count' => CustomerPaygoService::where('user_id', $user->id)->count(),
                'active_services_count' => CustomerPaygoService::where('user_id', $user->id)->where('is_active', true)->count(),
                'total_intents' => (int) (clone $intents)->count(),
                'paid_intents' => (int) ($statusCounts['paid'] ?? 0),
                'used_intents' => (int) ($statusCounts['used'] ?? 0),
                'pending_intents' => (int) ($statusCounts['pending'] ?? 0),
                'failed_intents' => (int) ($statusCounts['failed'] ?? 0),
                'expired_intents' => (int) ($statusCounts['expired'] ?? 0),
                'gross_revenue' => $paidAmount,
                'system_cost' => $systemCost,
                'net_earnings' => max(0, $paidAmount - $systemCost),
                'wallet_credits' => $walletCredits,
                'withdrawals' => $withdrawals,
            ],
            'daily' => $daily,
            'servicePerformance' => $servicePerformance,
        ]);
    }

    public function transactions(Request $request)
    {
        $user = $request->user();
        $serviceId = $request->integer('service') ?: null;

        $paymentIntentQuery = PaygoVerificationIntent::query()
            ->where('user_id', $user->id)
            ->with('paygoService:id,name,public_slug')
            ->when($serviceId, fn ($query) => $query->where('customer_paygo_service_id', $serviceId));

        $walletTransactionQuery = PaygoWalletTransaction::query()
            ->where('user_id', $user->id)
            ->when($serviceId, fn ($query) => $query->where('metadata->customer_paygo_service_id', $serviceId));

        return Inertia::render('Customer/Paygo/Transactions', [
            'filters' => [
                'service' => $serviceId,
            ],
            'services' => CustomerPaygoService::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get(['id', 'name', 'public_slug']),
            'walletTransactions' => $walletTransactionQuery
                ->latest()
                ->paginate(20, ['*'], 'wallet_page')
                ->withQueryString()
                ->through(fn (PaygoWalletTransaction $transaction) => [
                    'reference' => $transaction->reference,
                    'type' => $transaction->type,
                    'category' => $transaction->category,
                    'amount' => (float) $transaction->amount,
                    'balance_before' => (float) $transaction->balance_before,
                    'balance_after' => (float) $transaction->balance_after,
                    'description' => $transaction->description,
                    'status' => $transaction->status,
                    'metadata' => $transaction->metadata,
                    'created_at' => $transaction->created_at,
                ]),
            'paymentIntents' => $paymentIntentQuery
                ->latest()
                ->paginate(20, ['*'], 'payment_page')
                ->withQueryString()
                ->through(fn (PaygoVerificationIntent $intent) => [
                    'reference' => $intent->reference,
                    'service_name' => $intent->paygoService?->name,
                    'amount' => (float) $intent->amount,
                    'system_price' => (float) $intent->system_price_snapshot,
                    'earning' => max(0, (float) $intent->amount - (float) $intent->system_price_snapshot),
                    'status' => $intent->status,
                    'verification_attempts' => $intent->verification_attempts,
                    'max_fetches' => $intent->max_fetches_snapshot,
                    'reference_fetches' => $intent->reference_fetches,
                    'nin_last4' => $intent->metadata['nin_last4'] ?? null,
                    'lookup_label' => $intent->lookup_label,
                    'paid_at' => $intent->paid_at,
                    'used_at' => $intent->used_at,
                    'created_at' => $intent->created_at,
                ]),
        ]);
    }

    public function serviceTransactions(Request $request, CustomerPaygoService $paygoService)
    {
        $this->authorizeService($request, $paygoService);

        return response()->json([
            'payment_intents' => PaygoVerificationIntent::query()
                ->where('user_id', $request->user()->id)
                ->where('customer_paygo_service_id', $paygoService->id)
                ->latest()
                ->get()
                ->map(fn (PaygoVerificationIntent $intent) => [
                    'reference' => $intent->reference,
                    'amount' => (float) $intent->amount,
                    'system_price' => (float) $intent->system_price_snapshot,
                    'earning' => max(0, (float) $intent->amount - (float) $intent->system_price_snapshot),
                    'status' => $intent->status,
                    'verification_attempts' => $intent->verification_attempts,
                    'max_fetches' => $intent->max_fetches_snapshot,
                    'reference_fetches' => $intent->reference_fetches,
                    'nin_last4' => $intent->metadata['nin_last4'] ?? null,
                    'lookup_label' => $intent->lookup_label,
                    'paid_at' => $intent->paid_at,
                    'used_at' => $intent->used_at,
                    'created_at' => $intent->created_at,
                ]),
            'wallet_transactions' => PaygoWalletTransaction::query()
                ->where('user_id', $request->user()->id)
                ->where('metadata->customer_paygo_service_id', $paygoService->id)
                ->latest()
                ->get()
                ->map(fn (PaygoWalletTransaction $transaction) => [
                    'reference' => $transaction->reference,
                    'type' => $transaction->type,
                    'category' => $transaction->category,
                    'amount' => (float) $transaction->amount,
                    'balance_before' => (float) $transaction->balance_before,
                    'balance_after' => (float) $transaction->balance_after,
                    'description' => $transaction->description,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        if ($validated['verification_service_id'] === 'result') {
            return $this->storeResultPaygoServices($request, $validated);
        }

        if (blank($validated['name'] ?? null)) {
            return back()->withErrors(['name' => 'The service name field is required.']);
        }

        $verificationService = VerificationService::active()->whereKey($validated['verification_service_id'])->firstOrFail();
        $minimum = (float) $request->user()->getPriceForService($verificationService);

        if ((float) $validated['price'] <= $minimum) {
            return back()->withErrors(['price' => 'The public price must be above your system service price of NGN '.number_format($minimum, 2).'.']);
        }

        CustomerPaygoService::create([
            'user_id' => $request->user()->id,
            'verification_service_id' => $verificationService->id,
            'name' => $validated['name'],
            'public_slug' => CustomerPaygoService::generatePublicSlug($validated['name']),
            'verify_secret_hash' => hash('sha256', CustomerPaygoService::generateSecret()),
            'price' => $validated['price'],
            'is_active' => true,
            'success_url' => $validated['success_url'] ?? null,
            'failure_url' => $validated['failure_url'] ?? null,
            'response_mode' => $validated['response_mode'] ?? 'redirect',
        ]);

        return back()->with('success', 'Pay-on-the-go service created successfully.');
    }

    public function update(Request $request, CustomerPaygoService $paygoService)
    {
        $this->authorizeService($request, $paygoService);

        $validated = $this->validatePayload($request, $paygoService);

        if (blank($validated['name'] ?? null)) {
            return back()->withErrors(['name' => 'The service name field is required.']);
        }

        $verificationService = VerificationService::active()->whereKey($validated['verification_service_id'])->firstOrFail();
        $minimum = (float) $request->user()->getPriceForService($verificationService);

        if ((float) $validated['price'] <= $minimum) {
            return back()->withErrors(['price' => 'The public price must be above your system service price of NGN '.number_format($minimum, 2).'.']);
        }

        $paygoService->update([
            'verification_service_id' => $verificationService->id,
            'name' => $validated['name'],
            'price' => $validated['price'],
            'success_url' => $validated['success_url'] ?? null,
            'failure_url' => $validated['failure_url'] ?? null,
            'response_mode' => $validated['response_mode'] ?? $paygoService->response_mode ?? 'redirect',
            'is_active' => $validated['is_active'] ?? $paygoService->is_active,
        ]);

        return back()->with('success', 'Pay-on-the-go service updated successfully.');
    }

    public function toggle(Request $request, CustomerPaygoService $paygoService)
    {
        $this->authorizeService($request, $paygoService);
        $paygoService->update(['is_active' => ! $paygoService->is_active]);

        return back()->with('success', 'Pay-on-the-go service status updated.');
    }

    public function destroy(Request $request, CustomerPaygoService $paygoService)
    {
        $this->authorizeService($request, $paygoService);

        DB::transaction(function () use ($paygoService) {
            $paygoService->update(['is_active' => false]);
            $paygoService->delete();
        });

        return back()->with('success', 'Pay-on-the-go service deleted.');
    }

    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'bank_name' => 'required|string|max:120',
            'account_number' => 'required|string|max:30',
            'account_name' => 'required|string|max:120',
        ]);

        $wallet = PaygoWallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'pending_withdrawal' => 0, 'currency' => 'NGN', 'is_active' => true],
        );

        try {
            $wallet->requestWithdrawal((float) $validated['amount'], [
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'account_name' => $validated['account_name'],
            ]);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('success', 'PayGo withdrawal request submitted successfully.');
    }

    protected function validatePayload(Request $request, ?CustomerPaygoService $paygoService = null): array
    {
        return $request->validate([
            'name' => 'nullable|string|max:120',
            'verification_service_id' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $paygoService) {
                    if (! $paygoService && $value === 'result') {
                        if (! $request->user()->hasResultFetchAccess()) {
                            $fail('Result verification is not enabled for this account.');

                            return;
                        }

                        if ($this->activeResultFetchServices()->isEmpty()) {
                            $fail('No active result verification services are available.');
                        }

                        return;
                    }

                    if (! filter_var($value, FILTER_VALIDATE_INT)) {
                        $fail('Select a valid verification service.');

                        return;
                    }

                    $exists = VerificationService::active()
                        ->whereKey((int) $value)
                        ->where(function ($query) use ($request) {
                            $query->where('slug', 'nin');

                            if ($request->user()->hasResultFetchAccess()) {
                                $query->orWhere('slug', 'like', '%-result-fetch');
                            }
                        })
                        ->exists();

                    if (! $exists) {
                        $fail('Select a valid verification service.');
                    }
                },
            ],
            'price' => 'required|numeric|min:1',
            'success_url' => 'nullable|url|max:255',
            'failure_url' => 'nullable|url|max:255',
            'response_mode' => ['nullable', Rule::in(CustomerPaygoService::RESPONSE_MODES)],
            'is_active' => 'sometimes|boolean',
        ]);
    }

    protected function authorizeService(Request $request, CustomerPaygoService $paygoService): void
    {
        abort_unless($paygoService->user_id === $request->user()->id, 403);
    }

    protected function isResultBoardFetchService(VerificationService $service): bool
    {
        return (bool) preg_match('/^[a-z0-9-]+-result-fetch$/', $service->slug);
    }

    protected function boardFromService(VerificationService $service): string
    {
        return preg_replace('/-result-fetch$/', '', $service->slug);
    }

    protected function activeResultFetchServices()
    {
        return VerificationService::active()
            ->where('slug', 'like', '%-result-fetch')
            ->ordered()
            ->get();
    }

    protected function maxResultSystemPrice(Request $request): float
    {
        return (float) $this->activeResultFetchServices()
            ->map(fn (VerificationService $service) => (float) $request->user()->getPriceForService($service))
            ->max();
    }

    protected function paygoVerificationServiceOptions(Request $request)
    {
        $services = VerificationService::active()
            ->where('slug', 'nin')
            ->ordered()
            ->get()
            ->map(fn (VerificationService $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
                'service_type' => 'identity',
                'board' => null,
                'system_price' => (float) $request->user()->getPriceForService($service),
            ]);

        if ($request->user()->hasResultFetchAccess() && $this->activeResultFetchServices()->isNotEmpty()) {
            $services->push([
                'id' => 'result',
                'name' => 'Result Verification',
                'slug' => 'result-verification',
                'service_type' => 'result',
                'board' => null,
                'system_price' => $this->maxResultSystemPrice($request),
            ]);
        }

        return $services->values();
    }

    protected function storeResultPaygoServices(Request $request, array $validated)
    {
        $minimum = $this->maxResultSystemPrice($request);

        if ((float) $validated['price'] <= $minimum) {
            return back()->withErrors(['price' => 'The public price must be above your highest result verification system price of NGN '.number_format($minimum, 2).'.']);
        }

        DB::transaction(function () use ($request, $validated) {
            foreach ($this->activeResultFetchServices() as $service) {
                $board = strtoupper($this->boardFromService($service));
                $paygoService = CustomerPaygoService::firstOrNew([
                    'user_id' => $request->user()->id,
                    'verification_service_id' => $service->id,
                ]);

                if (! $paygoService->exists) {
                    $paygoService->public_slug = CustomerPaygoService::generatePublicSlug($board.' Result Verification');
                    $paygoService->verify_secret_hash = hash('sha256', CustomerPaygoService::generateSecret());
                }

                $paygoService->fill([
                    'name' => $board.' Result Verification',
                    'price' => $validated['price'],
                    'is_active' => true,
                    'success_url' => $validated['success_url'] ?? null,
                    'failure_url' => $validated['failure_url'] ?? null,
                    'response_mode' => $validated['response_mode'] ?? 'redirect',
                ])->save();
            }
        });

        return back()->with('success', 'Result verification PayGo pages created successfully.');
    }
}
