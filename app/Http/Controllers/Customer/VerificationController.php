<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Services\Verification\VerificationEngine;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $verification = VerificationRequest::query()
                ->where('user_id', $request->user()->id)
                ->where('verification_service_id', $service->id)
                ->where('search_parameter', $validated['search_parameter'])
                ->latest('id')
                ->first();

            return Inertia::render('Customer/Verification/Result', [
                'service' => $service,
                'result' => $result->toArray(),
                'searchParameter' => $validated['search_parameter'],
                'verification' => $verification,
            ]);
        }

        return back()->withErrors(['search_parameter' => $result->getErrorMessage() ?? 'Verification failed. Please try again.']);
    }

    public function history(Request $request)
    {
        $verifications = $this->historyQuery($request)
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

    public function exportHistory(Request $request)
    {
        $query = $this->historyQuery($request)->orderByDesc('id');

        return CsvExport::download(
            filename: 'verification-history-'.now()->format('Ymd-His').'.csv',
            headers: ['Reference', 'Service', 'Search Parameter', 'Status', 'Amount Charged', 'Created At', 'Completed At'],
            rows: function () use ($query) {
                foreach ($query->lazyByIdDesc(500, 'id') as $verification) {
                    yield [
                        $verification->reference,
                        $verification->verificationService?->name,
                        $verification->search_parameter,
                        $verification->status,
                        $verification->amount_charged,
                        $verification->created_at,
                        $verification->completed_at,
                    ];
                }
            },
        );
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

    public function download(VerificationRequest $verification, Request $request): StreamedResponse
    {
        if ($verification->user_id !== $request->user()->id) {
            abort(403);
        }

        $verification->loadMissing(['verificationService:id,name', 'serviceProvider:id,name']);

        $payload = [
            'reference' => $verification->reference,
            'service' => $verification->verificationService?->name,
            'provider' => $verification->serviceProvider?->name,
            'search_parameter' => $verification->search_parameter,
            'status' => $verification->status,
            'amount_charged' => $verification->amount_charged,
            'source' => $verification->source,
            'completed_at' => $verification->completed_at,
            'created_at' => $verification->created_at,
            'error_message' => $verification->error_message,
            'response_data' => $verification->response_data,
        ];

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, sprintf('verification-result-%s.json', $verification->reference), [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function historyQuery(Request $request)
    {
        return $request->user()->verificationRequests()
            ->select([
                'id',
                'user_id',
                'verification_service_id',
                'service_provider_id',
                'reference',
                'search_parameter',
                'amount_charged',
                'status',
                'source',
                'error_message',
                'response_data',
                'created_at',
                'completed_at',
            ])
            ->with('verificationService:id,name')
            ->when($request->filled('service'), fn (Builder $query) => $query->where('verification_service_id', $request->integer('service')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')));
    }
}
