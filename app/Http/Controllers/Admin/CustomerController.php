<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Models\VerificationService;
use App\Models\CustomerServicePricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

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
                      ->orWhereHas('customer', fn($c) => $c->where('company_name', 'like', "%{$search}%"));
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
            'company_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            $user->assignRole('customer');

            Customer::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'] ?? null,
                'business_type' => $validated['business_type'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
            ]);
        });

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function show(User $customer)
    {
        $customer->load(['customer', 'wallet', 'transactions' => fn($q) => $q->latest()->take(20)]);

        $verificationStats = $customer->verificationRequests()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $services = VerificationService::active()->get();
        $customPricing = $customer->customPricing()->with('verificationService')->get()
            ->keyBy('verification_service_id');

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
            'verificationStats' => $verificationStats,
            'services' => $services,
            'customPricing' => $customPricing,
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
            'email' => 'required|email|unique:users,email,' . $customer->id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'company_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($validated, $customer) {
            $customer->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $customer->customer?->update([
                'company_name' => $validated['company_name'] ?? null,
                'business_type' => $validated['business_type'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
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
}

