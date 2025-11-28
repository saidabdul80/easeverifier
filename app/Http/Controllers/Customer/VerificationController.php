<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Services\Verification\VerificationEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function __construct(
        protected VerificationEngine $verificationEngine
    ) {}

    public function index(Request $request)
    {
        $services = VerificationService::active()->ordered()->get()
            ->map(function ($service) use ($request) {
                $service->price = $request->user()->getPriceForService($service);
                return $service;
            });

        return Inertia::render('Customer/Verification/Index', [
            'services' => $services,
        ]);
    }

    public function show(VerificationService $service, Request $request)
    {
        if (!$service->is_active) {
            return redirect()->route('customer.verification.index')
                ->with('error', 'This service is not available.');
        }

        $price = $request->user()->getPriceForService($service);
        $walletBalance = $request->user()->wallet?->total_balance ?? 0;

        return Inertia::render('Customer/Verification/Show', [
            'service' => $service,
            'price' => $price,
            'walletBalance' => $walletBalance,
        ]);
    }

    public function verify(Request $request, VerificationService $service)
    {
        $validated = $request->validate([
            'search_parameter' => 'required|string|max:255',
        ]);

        if (!$service->is_active) {
            return back()->withErrors(['search_parameter' => 'This service is not available.']);
        }

        // Check if user has sufficient balance
        $price = $request->user()->getPriceForService($service);
        $walletBalance = $request->user()->wallet?->total_balance ?? 0;

        if ($walletBalance < $price) {
            return back()->withErrors(['search_parameter' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        // Check if providers exist
        if ($service->activeProviders()->count() === 0) {
            return back()->withErrors(['search_parameter' => 'No service providers configured. Please contact support.']);
        }

        $result = $this->verificationEngine->verify(
            user: $request->user(),
            service: $service,
            searchParameter: $validated['search_parameter'],
            source: 'web',
            ipAddress: $request->ip()
        );

        if ($result->isSuccessful()) {
            return Inertia::render('Customer/Verification/Result', [
                'service' => $service,
                'result' => $result->toArray(),
                'searchParameter' => $validated['search_parameter'],
            ]);
        }

        return back()->withErrors(['search_parameter' => $result->getErrorMessage() ?? 'Verification failed. Please try again.']);
    }

    public function history(Request $request)
    {
        $verifications = $request->user()->verificationRequests()
            ->with('verificationService')
            ->when($request->service, fn($q, $service) => $q->where('verification_service_id', $service))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $services = VerificationService::active()->get();

        return Inertia::render('Customer/Verification/History', [
            'verifications' => $verifications,
            'services' => $services,
            'filters' => $request->only(['service', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function showResult(VerificationRequest $verification, Request $request)
    {
        // Ensure user can only see their own verifications
        if ($verification->user_id !== $request->user()->id) {
            abort(403);
        }

        $verification->load('verificationService');

        return Inertia::render('Customer/Verification/Result', [
            'service' => $verification->verificationService,
            'result' => [
                'success' => $verification->status === 'completed',
                'data' => $verification->response_data,
                'error_message' => $verification->error_message,
            ],
            'searchParameter' => $verification->search_parameter,
            'verification' => $verification,
        ]);
    }
}

