<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPaygoService;
use App\Models\CustomerResultPinPricing;
use App\Models\CustomerServicePricing;
use App\Models\ResultPinProduct;
use App\Models\User;
use App\Models\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $customer->load(['customer', 'wallet', 'transactions' => fn ($q) => $q->latest()->take(20)]);

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
                    'suggested_price' => $paygoService ? (float) $paygoService->price : $systemPrice + 100,
                    'paygo_service' => $paygoService ? [
                        'id' => $paygoService->id,
                        'name' => $paygoService->name,
                        'price' => (float) $paygoService->price,
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
        ]);

        $profile = $customer->customer()->firstOrNew(['user_id' => $customer->id]);
        $profile->result_fetch_enabled = $validated['enabled'];
        $profile->paygo_result_reference_fetch_limit = $validated['paygo_result_reference_fetch_limit'];

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
            'is_active' => 'required|boolean',
        ]);

        $minimum = (float) $customer->getPriceForService($service);

        if ((float) $validated['price'] <= $minimum) {
            return back()->withErrors([
                'price' => 'The public price must be above this customer system price of NGN '.number_format($minimum, 2).'.',
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
            'is_active' => $validated['is_active'],
        ])->save();

        return back()->with('success', 'Customer PayGo result page updated successfully.');
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
