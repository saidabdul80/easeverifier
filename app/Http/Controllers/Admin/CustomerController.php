<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPaygoService;
use App\Models\CustomerResultPinPricing;
use App\Models\CustomerServicePricing;
use App\Models\CustomerPaystackSplitAccount;
use App\Models\PaystackGatewayAccount;
use App\Models\ResultPinProduct;
use App\Models\User;
use App\Models\VerificationService;
use App\Services\PaystackService;
use App\Services\PaystackGatewayResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = User::role('customer')
            ->with(['customer', 'wallet'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status !== null, function ($query) use ($request) {
                $query->where('is_active', $request->status === 'active');
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Customers/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'account_type' => ['required', Rule::in(Customer::ACCOUNT_TYPES)],
            'company_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'registration_number' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('account_type') === 'business')],
            'address' => ['nullable', 'string', Rule::requiredIf($request->input('account_type') === 'business')],
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'website' => ['nullable', 'url', 'max:255', Rule::requiredIf($request->input('account_type') === 'business')],
            'use_case' => ['nullable', 'string', Rule::requiredIf($request->input('account_type') === 'business')],
            'expected_monthly_volume' => [
                'nullable',
                'string',
                Rule::in(Customer::EXPECTED_MONTHLY_VOLUMES),
                Rule::requiredIf($request->input('account_type') === 'business'),
            ],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            Role::findOrCreate('customer');
            $user->assignRole('customer');

            Customer::create([
                'user_id' => $user->id,
                'account_type' => $validated['account_type'],
                'company_name' => $validated['company_name'] ?? null,
                'business_type' => $validated['business_type'] ?? null,
                'registration_number' => $validated['account_type'] === 'business' ? ($validated['registration_number'] ?? null) : null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'website' => $validated['account_type'] === 'business' ? ($validated['website'] ?? null) : null,
                'use_case' => $validated['account_type'] === 'business' ? ($validated['use_case'] ?? null) : null,
                'expected_monthly_volume' => $validated['account_type'] === 'business' ? ($validated['expected_monthly_volume'] ?? null) : null,
            ]);
        });

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function show(User $customer)
    {
        $customer->load([
            'customer.paystackSplitAccounts' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'customer.paystackGatewayAccounts' => fn ($query) => $query->latest('id'),
            'wallet',
            'transactions' => fn ($q) => $q->latest()->take(20),
        ]);

        $verificationStats = $customer->verificationRequests()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count)
            ->toArray();

        $verificationStats['total'] = array_sum($verificationStats);

        $services = VerificationService::active()->get();
        $customPricing = $customer->customPricing()->with('verificationService')->get()
            ->keyBy('verification_service_id');
        $resultPinProducts = ResultPinProduct::active()->ordered()->get();
        $resultPinPricing = $customer->resultPinPricing()->with('product')->get()
            ->keyBy('result_pin_product_id');
        $existingResultPaygoServices = CustomerPaygoService::query()
            ->with('verificationService')
            ->where('user_id', $customer->id)
            ->whereHas('verificationService', fn ($query) => $query->where('slug', 'like', '%-result-fetch'))
            ->get()
            ->keyBy('verification_service_id');
        $paygoResultServices = VerificationService::active()
            ->where('slug', 'like', '%-result-fetch')
            ->orderBy('name')
            ->get()
            ->map(function (VerificationService $service) use ($customer, $existingResultPaygoServices) {
                $paygoService = $existingResultPaygoServices->get($service->id);
                $systemPrice = $customer->getPriceForService($service);

                return [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'service_slug' => $service->slug,
                    'board' => $this->boardFromResultFetchService($service),
                    'system_price' => $systemPrice,
                    'reference_system_price' => $paygoService?->resultReferenceSystemPrice()
                        ?? ($customer->customer?->paygoResultReferenceSystemPrice((float) $systemPrice) ?? max(1, (float) $systemPrice * 2)),
                    'suggested_price' => $paygoService ? (float) $paygoService->price : $systemPrice + 100,
                    'suggested_reference_price' => $paygoService ? (float) $paygoService->resultReferencePrice() : max((float) $systemPrice + 100, ($customer->customer?->paygoResultReferenceSystemPrice((float) $systemPrice) ?? max(1, (float) $systemPrice * 2)) + 100),
                    'paygo_service' => $paygoService ? [
                        'id' => $paygoService->id,
                        'name' => $paygoService->name,
                        'price' => (float) $paygoService->price,
                        'reference_price' => (float) $paygoService->resultReferencePrice(),
                        'is_active' => $paygoService->is_active,
                        'public_slug' => $paygoService->public_slug,
                        'result_url' => $paygoService->resultUrl(),
                        'selector_url' => $paygoService->resultSelectorUrl(),
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
            'verificationStats' => $verificationStats,
            'services' => $services,
            'customPricing' => $customPricing,
            'resultPinProducts' => $resultPinProducts,
            'resultPinPricing' => $resultPinPricing,
            'paygoResultServices' => $paygoResultServices,
            'paystackSplitAccounts' => $customer->customer?->paystackSplitAccounts
                ?->where('beneficiary_type', 'customer')
                ?->map(fn ($account) => [
                    'id' => $account->id,
                    'label' => $account->label,
                    'subaccount_code' => $account->subaccount_code,
                    'account_name' => $account->account_name,
                    'bank_name' => $account->bank_name,
                    'bank_code' => $account->bank_code,
                    'account_number_last4' => $account->account_number_last4,
                    'flat_amount' => (float) $account->flat_amount,
                    'is_active' => $account->is_active,
                ])
                ->values() ?? [],
            'paystackGateway' => $this->paystackGatewayPayload($customer->customer),
        ]);
    }

    public function edit(User $customer)
    {
        $customer->load('customer');

        return Inertia::render('Admin/Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, User $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$customer->id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'account_type' => ['required', Rule::in(Customer::ACCOUNT_TYPES)],
            'company_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'registration_number' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('account_type') === 'business')],
            'address' => ['nullable', 'string', Rule::requiredIf($request->input('account_type') === 'business')],
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'website' => ['nullable', 'url', 'max:255', Rule::requiredIf($request->input('account_type') === 'business')],
            'use_case' => ['nullable', 'string', Rule::requiredIf($request->input('account_type') === 'business')],
            'expected_monthly_volume' => [
                'nullable',
                'string',
                Rule::in(Customer::EXPECTED_MONTHLY_VOLUMES),
                Rule::requiredIf($request->input('account_type') === 'business'),
            ],
        ]);

        DB::transaction(function () use ($validated, $customer) {
            $customer->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $customer->customer()->updateOrCreate(['user_id' => $customer->id], [
                'account_type' => $validated['account_type'],
                'company_name' => $validated['company_name'] ?? null,
                'business_type' => $validated['business_type'] ?? null,
                'registration_number' => $validated['account_type'] === 'business' ? ($validated['registration_number'] ?? null) : null,
                'address' => $validated['account_type'] === 'business' ? ($validated['address'] ?? null) : null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'website' => $validated['account_type'] === 'business' ? ($validated['website'] ?? null) : null,
                'use_case' => $validated['account_type'] === 'business' ? ($validated['use_case'] ?? null) : null,
                'expected_monthly_volume' => $validated['account_type'] === 'business' ? ($validated['expected_monthly_volume'] ?? null) : null,
            ]);
        });

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function updatePricing(Request $request, User $customer)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:verification_services,id',
            'price' => 'required|numeric|min:0',
        ]);

        CustomerServicePricing::updateOrCreate(
            [
                'user_id' => $customer->id,
                'verification_service_id' => $validated['service_id'],
            ],
            ['price' => $validated['price'], 'is_active' => true]
        );

        return back()->with('success', 'Pricing updated successfully.');
    }

    public function updateResultPinPricing(Request $request, User $customer)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:result_pin_products,id',
            'price' => 'required|numeric|min:0',
        ]);

        CustomerResultPinPricing::updateOrCreate(
            [
                'user_id' => $customer->id,
                'result_pin_product_id' => $validated['product_id'],
            ],
            ['price' => $validated['price'], 'is_active' => true],
        );

        return back()->with('success', 'Result PIN pricing updated successfully.');
    }

    public function updateResultFetchAccess(Request $request, User $customer)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'paygo_result_reference_fetch_limit' => 'required|integer|min:1|max:50',
            'paygo_result_reference_system_price' => 'nullable|numeric|min:0',
        ]);

        $profile = $customer->customer()->firstOrNew(['user_id' => $customer->id]);
        $profile->result_fetch_enabled = $validated['enabled'];
        $profile->paygo_result_reference_fetch_limit = $validated['paygo_result_reference_fetch_limit'];
        $profile->paygo_result_reference_system_price = $validated['paygo_result_reference_system_price'] ?? null;

        if (! $profile->account_type) {
            $profile->account_type = 'individual';
        }

        $profile->save();

        return back()->with('success', 'Result board fetch access updated successfully.');
    }

    public function updateResultPaygoService(Request $request, User $customer, VerificationService $service)
    {
        abort_unless($service->is_active && $this->isResultFetchService($service), 404);

        $validated = $request->validate([
            'name' => 'nullable|string|max:120',
            'price' => 'required|numeric|min:1',
            'reference_price' => 'nullable|numeric|min:1',
            'is_active' => 'required|boolean',
        ]);

        $minimum = (float) $customer->getPriceForService($service);
        $referenceMinimum = $customer->customer?->paygoResultReferenceSystemPrice($minimum) ?? max(1, $minimum * 2);

        if ((float) $validated['price'] <= $minimum) {
            return back()->withErrors([
                'price' => 'The public price must be above this customer system price of NGN '.number_format($minimum, 2).'.',
            ]);
        }

        if (filled($validated['reference_price'] ?? null) && (float) $validated['reference_price'] <= $referenceMinimum) {
            return back()->withErrors([
                'reference_price' => 'The portal reference package price must be above NGN '.number_format($referenceMinimum, 2).'.',
            ]);
        }

        $paygoService = CustomerPaygoService::firstOrNew([
            'user_id' => $customer->id,
            'verification_service_id' => $service->id,
        ]);

        if (! $paygoService->exists) {
            $paygoService->public_slug = CustomerPaygoService::generatePublicSlug($validated['name'] ?: $service->name);
            $paygoService->verify_secret_hash = hash('sha256', CustomerPaygoService::generateSecret());
            $paygoService->success_url = null;
            $paygoService->failure_url = null;
            $paygoService->response_mode = 'redirect';
        }

        $paygoService->fill([
            'name' => $validated['name'] ?: strtoupper($this->boardFromResultFetchService($service)).' Result Verification',
            'price' => $validated['price'],
            'reference_price' => $validated['reference_price'] ?? null,
            'is_active' => $validated['is_active'],
        ])->save();

        return back()->with('success', 'Customer PayGo result page updated successfully.');
    }

    public function paystackBanks(PaystackService $paystack)
    {
        $result = $paystack->listBanks('nigeria');

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Unable to fetch Paystack banks.',
            ], 422);
        }

        return response()->json([
            'banks' => $result['banks'],
        ]);
    }

    public function updatePaystackSplits(Request $request, User $customer, PaystackService $paystack)
    {
        $validated = $request->validate([
            'splits' => ['nullable', 'array', 'max:2'],
            'splits.*.id' => ['nullable', 'integer'],
            'splits.*.label' => ['nullable', 'string', 'max:120'],
            'splits.*.subaccount_code' => ['nullable', 'string', 'max:80'],
            'splits.*.account_name' => ['nullable', 'string', 'max:150'],
            'splits.*.bank_name' => ['required', 'string', 'max:120'],
            'splits.*.bank_code' => ['required', 'string', 'max:20'],
            'splits.*.account_number' => ['nullable', 'digits:10'],
            'splits.*.flat_amount' => ['required', 'numeric', 'min:0.01'],
            'splits.*.is_active' => ['required', 'boolean'],
        ]);

        $profile = $customer->customer()->firstOrNew(['user_id' => $customer->id]);

        if (! $profile->account_type) {
            $profile->account_type = 'individual';
        }

        $profile->save();

        $splits = array_values($validated['splits'] ?? []);
        $activeSplitTotalKobo = collect($splits)
            ->filter(fn (array $split) => (bool) ($split['is_active'] ?? false))
            ->sum(fn (array $split) => (int) round((float) $split['flat_amount'] * 100));

        if ($activeSplitTotalKobo > 0 && $activeSplitTotalKobo < 100) {
            throw ValidationException::withMessages([
                'splits' => 'The active Paystack split total must be at least NGN 1.00.',
            ]);
        }

        $existingIds = collect($splits)->pluck('id')->filter()->values();
        $existingAccounts = $profile->paystackSplitAccounts()
            ->whereNull('paystack_gateway_account_id')
            ->where('beneficiary_type', 'customer')
            ->whereIn('id', $existingIds)
            ->get()
            ->keyBy('id');
        $preparedSplits = [];

        foreach ($splits as $index => $split) {
            $existing = isset($split['id']) ? $existingAccounts->get($split['id']) : null;

            if (isset($split['id']) && ! $existing) {
                throw ValidationException::withMessages([
                    "splits.{$index}.id" => 'This split account does not belong to the selected customer.',
                ]);
            }

            $accountNumber = isset($split['account_number'])
                ? preg_replace('/\D/', '', (string) $split['account_number'])
                : '';
            $needsSubaccount = filled($accountNumber)
                || ! $existing
                || blank($existing->subaccount_code)
                || $existing->bank_code !== $split['bank_code'];
            $paystackSubaccount = null;
            $resolvedAccount = null;

            if ($needsSubaccount) {
                if (blank($accountNumber)) {
                    throw ValidationException::withMessages([
                        "splits.{$index}.account_number" => 'Enter the 10-digit account number for this Paystack split.',
                    ]);
                }

                $resolvedAccount = $paystack->resolveBankAccount($accountNumber, $split['bank_code']);

                if (! $resolvedAccount['success']) {
                    throw ValidationException::withMessages([
                        "splits.{$index}.account_number" => $resolvedAccount['message'] ?? 'Paystack could not resolve this bank account.',
                    ]);
                }

                $paystackSubaccount = $paystack->createSubaccount(
                    businessName: $this->paystackSplitBusinessName($customer, $split, $index),
                    bankCode: $split['bank_code'],
                    accountNumber: $accountNumber,
                    description: 'Easeverifier PayGo flat split for '.$customer->name,
                    metadata: [
                        'customer_user_id' => $customer->id,
                        'customer_profile_id' => $profile->id,
                        'split_label' => $split['label'] ?? null,
                    ],
                );

                if (! $paystackSubaccount['success'] || blank($paystackSubaccount['subaccount_code'] ?? null)) {
                    throw ValidationException::withMessages([
                        "splits.{$index}.account_number" => $paystackSubaccount['message'] ?? 'Paystack could not create a subaccount for this bank account.',
                    ]);
                }
            }

            $preparedSplits[] = [
                'existing' => $existing,
                'split' => $split,
                'account_number' => $accountNumber,
                'resolved_account' => $resolvedAccount,
                'paystack_subaccount' => $paystackSubaccount,
            ];
        }

        DB::transaction(function () use ($profile, $preparedSplits) {
            $savedIds = [];

            foreach ($preparedSplits as $index => $prepared) {
                $split = $prepared['split'];
                $existing = $prepared['existing'];
                $paystackSubaccount = $prepared['paystack_subaccount'];
                $resolvedAccount = $prepared['resolved_account'];
                $accountNumber = $prepared['account_number'];
                $account = $existing ?: $profile->paystackSplitAccounts()->make();
                $creatingSubaccount = is_array($paystackSubaccount);

                $account->fill([
                    'label' => $split['label'] ?? null,
                    'bank_name' => $split['bank_name'],
                    'bank_code' => $split['bank_code'],
                    'flat_amount' => $split['flat_amount'],
                    'sort_order' => $index + 1,
                    'is_active' => $split['is_active'],
                ]);

                if ($creatingSubaccount) {
                    $account->fill([
                        'subaccount_code' => $paystackSubaccount['subaccount_code'],
                        'account_name' => $paystackSubaccount['account_name'] ?: ($resolvedAccount['account_name'] ?? ($split['account_name'] ?? null)),
                        'account_number' => $accountNumber,
                        'account_number_last4' => substr($accountNumber, -4),
                        'metadata' => array_filter([
                            'paystack_resolved_account' => $resolvedAccount['data'] ?? null,
                            'paystack_subaccount' => $paystackSubaccount['data'] ?? null,
                        ]),
                    ]);
                } else {
                    $account->account_name = $split['account_name'] ?? $account->account_name;
                }

                $account->save();
                $savedIds[] = $account->id;
            }

            $profile->paystackSplitAccounts()
                ->whereNull('paystack_gateway_account_id')
                ->where('beneficiary_type', 'customer')
                ->when($savedIds !== [], fn ($query) => $query->whereNotIn('id', $savedIds))
                ->delete();
        });

        return back()->with('success', 'Paystack split accounts updated successfully.');
    }

    public function updatePaystackGateway(Request $request, User $customer, PaystackGatewayResolver $resolver)
    {
        $profile = $customer->customer;
        abort_unless($profile, 404);

        $existing = $profile->paystackGatewayAccounts()
            ->where('owner_type', 'customer')
            ->where('environment', $request->string('environment')->value())
            ->latest('id')
            ->first();

        $validated = $request->validate([
            'environment' => ['required', Rule::in(PaystackGatewayAccount::ENVIRONMENTS)],
            'public_key' => [$existing ? 'nullable' : 'required', 'string', 'max:255'],
            'secret_key' => [$existing ? 'nullable' : 'required', 'string', 'max:255'],
            'is_trusted' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'system_bank_name' => ['required', 'string', 'max:120'],
            'system_bank_code' => ['required', 'string', 'max:20'],
            'system_account_number' => [($this->gatewayHasSystemSubaccount($existing) && ! $request->filled('secret_key')) ? 'nullable' : 'required', 'nullable', 'digits:10'],
        ]);

        $publicKey = filled($validated['public_key'] ?? null) ? trim($validated['public_key']) : $existing?->public_key;
        $secretKey = filled($validated['secret_key'] ?? null) ? trim($validated['secret_key']) : $existing?->secret_key;
        $prefix = $validated['environment'] === 'live' ? 'live' : 'test';

        if (! str_starts_with((string) $publicKey, "pk_{$prefix}_") || ! str_starts_with((string) $secretKey, "sk_{$prefix}_")) {
            throw ValidationException::withMessages([
                'secret_key' => 'The public and secret keys must both match the selected Paystack environment.',
            ]);
        }

        $paystack = PaystackService::withCredentials($secretKey, $publicKey);
        $connection = $paystack->verifyCredentials();

        if (! ($connection['success'] ?? false)) {
            throw ValidationException::withMessages([
                'secret_key' => $connection['message'] ?? 'Paystack rejected these credentials.',
            ]);
        }

        $accountNumber = preg_replace('/\D/', '', (string) ($validated['system_account_number'] ?? ''));
        $rotating = $existing && filled($validated['secret_key'] ?? null)
            && $existing->key_fingerprint !== PaystackGatewayAccount::fingerprint($secretKey);
        $needsSubaccount = $rotating || filled($accountNumber) || ! $this->gatewayHasSystemSubaccount($existing);
        $resolved = null;
        $subaccount = null;

        if ($needsSubaccount) {
            $resolved = $paystack->resolveBankAccount($accountNumber, $validated['system_bank_code']);

            if (! ($resolved['success'] ?? false)) {
                throw ValidationException::withMessages([
                    'system_account_number' => $resolved['message'] ?? 'Paystack could not resolve the EaseVerifier settlement account.',
                ]);
            }

            $subaccount = $paystack->createSubaccount(
                businessName: 'EaseVerifier Settlement',
                bankCode: $validated['system_bank_code'],
                accountNumber: $accountNumber,
                description: 'EaseVerifier system settlement for '.$customer->name,
                metadata: ['beneficiary_type' => 'system', 'customer_user_id' => $customer->id],
            );

            if (! ($subaccount['success'] ?? false) || blank($subaccount['subaccount_code'] ?? null)) {
                throw ValidationException::withMessages([
                    'system_account_number' => $subaccount['message'] ?? 'Paystack could not create the EaseVerifier settlement subaccount.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $profile, $existing, $validated, $publicKey, $secretKey, $accountNumber, $resolved, $subaccount, $rotating) {
            if ($rotating) {
                $existing->update(['is_active' => false, 'rotated_at' => now()]);
            }

            $gateway = ($existing && ! $rotating) ? $existing : new PaystackGatewayAccount([
                'owner_type' => 'customer',
                'customer_id' => $profile->id,
                'environment' => $validated['environment'],
            ]);

            $gateway->fill([
                'label' => $profile->company_name ?: 'Customer Paystack',
                'public_key' => $publicKey,
                'secret_key' => $secretKey,
                'key_fingerprint' => PaystackGatewayAccount::fingerprint($secretKey),
                'is_trusted' => $validated['is_trusted'],
                'is_active' => $validated['is_active'],
                'verification_status' => 'verified',
                'verified_at' => $gateway->verified_at ?? now(),
                'last_verified_at' => now(),
                'created_by_admin_id' => $gateway->created_by_admin_id ?? $request->user()->id,
                'rotated_at' => null,
            ])->save();

            if ($subaccount) {
                $profile->paystackSplitAccounts()
                    ->where('paystack_gateway_account_id', $gateway->id)
                    ->where('beneficiary_type', 'system')
                    ->update(['is_active' => false]);

                CustomerPaystackSplitAccount::create([
                    'customer_id' => $profile->id,
                    'paystack_gateway_account_id' => $gateway->id,
                    'beneficiary_type' => 'system',
                    'label' => 'EaseVerifier system settlement',
                    'subaccount_code' => $subaccount['subaccount_code'],
                    'account_name' => $subaccount['account_name'] ?: ($resolved['account_name'] ?? null),
                    'bank_name' => $validated['system_bank_name'],
                    'bank_code' => $validated['system_bank_code'],
                    'account_number' => $accountNumber,
                    'account_number_last4' => substr($accountNumber, -4),
                    'flat_amount' => 0.01,
                    'sort_order' => 1,
                    'is_active' => true,
                    'metadata' => ['paystack_subaccount' => $subaccount['data'] ?? null],
                ]);
            }
        });

        return back()->with('success', 'Customer Paystack gateway verified and saved.');
    }

    protected function paystackGatewayPayload(?Customer $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        $gateway = $profile->paystackGatewayAccounts->first();
        if (! $gateway) {
            return null;
        }

        $systemSubaccount = $profile->paystackSplitAccounts
            ->first(fn ($account) => (int) $account->paystack_gateway_account_id === (int) $gateway->id && $account->beneficiary_type === 'system' && $account->is_active);

        return [
            'id' => $gateway->id,
            'environment' => $gateway->environment,
            'key_fingerprint' => $gateway->key_fingerprint,
            'is_trusted' => $gateway->is_trusted,
            'is_active' => $gateway->is_active,
            'verification_status' => $gateway->verification_status,
            'last_verified_at' => $gateway->last_verified_at,
            'system_subaccount' => $systemSubaccount ? [
                'subaccount_code' => $systemSubaccount->subaccount_code,
                'bank_name' => $systemSubaccount->bank_name,
                'bank_code' => $systemSubaccount->bank_code,
                'account_name' => $systemSubaccount->account_name,
                'account_number_last4' => $systemSubaccount->account_number_last4,
            ] : null,
        ];
    }

    protected function gatewayHasSystemSubaccount(?PaystackGatewayAccount $gateway): bool
    {
        return $gateway?->subaccounts()
            ->where('beneficiary_type', 'system')
            ->where('is_active', true)
            ->exists() ?? false;
    }

    protected function paystackSplitBusinessName(User $customer, array $split, int $index): string
    {
        $name = trim((string) ($split['label'] ?? ''));

        if ($name === '') {
            $name = trim((string) ($split['account_name'] ?? ''));
        }

        if ($name === '') {
            $name = trim((string) ($customer->customer?->company_name ?: $customer->name));
        }

        return str($name.' Split '.($index + 1))->limit(120, '')->toString();
    }

    protected function isResultFetchService(VerificationService $service): bool
    {
        return (bool) preg_match('/^[a-z0-9-]+-result-fetch$/', $service->slug);
    }

    protected function boardFromResultFetchService(VerificationService $service): string
    {
        return preg_replace('/-result-fetch$/', '', $service->slug);
    }
}
