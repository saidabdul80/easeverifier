<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\VerificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProviderController extends Controller
{
    public function create(VerificationService $service)
    {
        return Inertia::render('Admin/Providers/Create', [
            'service' => $service,
            'authTypes' => [
                ['value' => 'none', 'label' => 'No Authentication'],
                ['value' => 'bearer', 'label' => 'Bearer Token'],
                ['value' => 'api_key_header', 'label' => 'API Key in Header'],
                ['value' => 'api_key_body', 'label' => 'API Key in Body'],
                ['value' => 'basic', 'label' => 'Basic Authentication'],
                ['value' => 'custom', 'label' => 'Custom Headers'],
            ],
            'httpMethods' => ['GET', 'POST', 'PUT', 'PATCH'],
        ]);
    }

    public function store(Request $request, VerificationService $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url',
            'endpoint' => 'required|string',
            'http_method' => 'required|in:GET,POST,PUT,PATCH',
            'auth_type' => 'required|in:none,bearer,api_key_header,api_key_body,basic,custom',
            'auth_config' => 'nullable|array',
            'request_headers' => 'nullable|array',
            'request_body_template' => 'nullable|array',
            'response_mapping' => 'nullable|array',
            'timeout' => 'integer|min:5|max:120',
            'priority' => 'integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['verification_service_id'] = $service->id;

        ServiceProvider::create($validated);

        return redirect()->route('admin.services.show', $service)
            ->with('success', 'Provider added successfully.');
    }

    public function edit(ServiceProvider $provider)
    {
        $provider->load('verificationService');

        return Inertia::render('Admin/Providers/Edit', [
            'provider' => $provider,
            'service' => $provider->verificationService,
            'authTypes' => [
                ['value' => 'none', 'label' => 'No Authentication'],
                ['value' => 'bearer', 'label' => 'Bearer Token'],
                ['value' => 'api_key_header', 'label' => 'API Key in Header'],
                ['value' => 'api_key_body', 'label' => 'API Key in Body'],
                ['value' => 'basic', 'label' => 'Basic Authentication'],
                ['value' => 'custom', 'label' => 'Custom Headers'],
            ],
            'httpMethods' => ['GET', 'POST', 'PUT', 'PATCH'],
        ]);
    }

    public function update(Request $request, ServiceProvider $provider)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url',
            'endpoint' => 'required|string',
            'http_method' => 'required|in:GET,POST,PUT,PATCH',
            'auth_type' => 'required|in:none,bearer,api_key_header,api_key_body,basic,custom',
            'auth_config' => 'nullable|array',
            'request_headers' => 'nullable|array',
            'request_body_template' => 'nullable|array',
            'response_mapping' => 'nullable|array',
            'timeout' => 'integer|min:5|max:120',
            'priority' => 'integer|min:1',
            'is_active' => 'boolean',
        ]);

        $provider->update($validated);

        return redirect()->route('admin.services.show', $provider->verification_service_id)
            ->with('success', 'Provider updated successfully.');
    }

    public function destroy(ServiceProvider $provider)
    {
        $serviceId = $provider->verification_service_id;
        $provider->delete();

        return redirect()->route('admin.services.show', $serviceId)
            ->with('success', 'Provider deleted successfully.');
    }

    public function toggleStatus(ServiceProvider $provider)
    {
        $provider->update(['is_active' => !$provider->is_active]);

        return back()->with('success', 'Provider status updated.');
    }
}

