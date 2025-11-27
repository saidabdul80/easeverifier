<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationService;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function index()
    {
        $services = VerificationService::withCount(['providers', 'verificationRequests'])
            ->ordered()
            ->get();

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Services/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:50|unique:verification_services,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'default_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        VerificationService::create($validated);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created successfully.');
    }

    public function show(VerificationService $service)
    {
        $service->load('providers');

        $stats = [
            'total_requests' => $service->verificationRequests()->count(),
            'successful_requests' => $service->verificationRequests()->where('status', 'completed')->count(),
            'failed_requests' => $service->verificationRequests()->where('status', 'failed')->count(),
            'total_revenue' => $service->verificationRequests()
                ->where('status', 'completed')
                ->sum('amount_charged'),
        ];

        return Inertia::render('Admin/Services/Show', [
            'service' => $service,
            'stats' => $stats,
        ]);
    }

    public function edit(VerificationService $service)
    {
        return Inertia::render('Admin/Services/Edit', [
            'service' => $service,
        ]);
    }

    public function update(Request $request, VerificationService $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:50|unique:verification_services,slug,' . $service->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'default_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $service->update($validated);

        return redirect()->route('admin.services.show', $service)
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(VerificationService $service)
    {
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully.');
    }
}

