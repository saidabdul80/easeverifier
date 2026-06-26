<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Services\ResultVerify\ResultFactory;
use App\Services\ResultVerify\ResultGates\NbaisResult;
use App\Services\ResultVerify\ResultVerificationEngine;
use App\Services\Verification\VerificationEngine;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function __construct(
        protected VerificationEngine $verificationEngine,
        protected ResultVerificationEngine $resultVerificationEngine,
        protected ResultFactory $resultFactory,
    ) {}

    public function index(Request $request)
    {
        $services = VerificationService::active()
            ->where('slug', 'not like', '%-result-form')
            ->when(! $request->user()->hasResultFetchAccess(), fn (Builder $query) => $query->where('slug', 'not like', '%-result-fetch'))
            ->ordered()
            ->get()
            ->map(function ($service) use ($request) {
                $service->price = $request->user()->getPriceForService($service);
                $this->applyResultBoardDisplayName($service);
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

        $isResultBoard = $this->isResultBoardFetchService($service);
        if ($isResultBoard && ! $request->user()->hasResultFetchAccess()) {
            return redirect()->route('customer.verification.index')
                ->with('error', 'Result board verification is not enabled for your account.');
        }

        $this->applyResultBoardDisplayName($service);

        $price = $request->user()->getPriceForService($service);
        $walletBalance = $request->user()->wallet?->total_balance ?? 0;
        $branches = $request->user()->branches()
            ->where('is_active', true)
            ->with('wallet')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'wallet_balance' => (float) ($branch->wallet?->total_balance ?? 0),
            ]);

        return Inertia::render('Customer/Verification/Show', [
            'service' => $service,
            'price' => $price,
            'walletBalance' => $walletBalance,
            'branches' => $branches,
            'isResultBoard' => $isResultBoard,
            'resultBoard' => $isResultBoard ? $this->boardFromService($service) : null,
            'formEndpoint' => $isResultBoard ? route('customer.verification.result-form', $service, false) : null,
        ]);
    }

    public function verify(Request $request, VerificationService $service)
    {
        if ($this->isResultBoardFetchService($service)) {
            return $this->verifyResultBoard($request, $service);
        }

        $validated = $request->validate([
            'search_parameter' => 'required|string|max:255',
            'branch_id' => 'nullable|integer',
        ]);

        $branch = null;

        if (! empty($validated['branch_id'])) {
            $branch = $request->user()->branches()
                ->where('is_active', true)
                ->whereKey($validated['branch_id'])
                ->with('wallet')
                ->firstOrFail();
        }

        if (!$service->is_active) {
            return back()->withErrors(['search_parameter' => 'This service is not available.']);
        }

        // Check if user has sufficient balance
        $price = $request->user()->getPriceForService($service);
        $walletBalance = $branch?->wallet?->total_balance ?? $request->user()->wallet?->total_balance ?? 0;

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
            ipAddress: $request->ip(),
            branch: $branch,
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

    public function resultFormFields(VerificationService $service): JsonResponse
    {
        abort_unless($service->is_active && $this->isResultBoardFetchService($service), 404);
        abort_unless(request()->user()?->hasResultFetchAccess(), 403);

        $board = $this->boardFromService($service);
        $fields = $this->resultFactory->create($board)->formFields();

        if ($board === 'nbais') {
            $fields = collect($fields)
                ->map(function (array $field) use ($service) {
                    if (($field['name'] ?? null) === 'sub_cat') {
                        $field['options_endpoint'] = route('customer.verification.result-schools', $service, false);
                    }

                    return $field;
                })
                ->all();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'board' => strtoupper($board),
                'fields' => $fields,
            ],
        ]);
    }

    public function resultSchools(Request $request, VerificationService $service, NbaisResult $nbaisResult): JsonResponse
    {
        abort_unless($service->is_active && $this->boardFromService($service) === 'nbais', 404);
        abort_unless($request->user()->hasResultFetchAccess(), 403);

        $validated = $request->validate([
            'parent_cat' => 'required|string|max:10',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $nbaisResult->fetchSchools($validated['parent_cat']),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
                'error_code' => 'SCHOOL_LOOKUP_FAILED',
            ], 400);
        }
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
            'branches' => $request->user()->branches()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'filters' => $request->only(['branch', 'service', 'status', 'date_from', 'date_to']),
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
                'branch_id',
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
            ->with('branch:id,name,code')
            ->when(
                $request->string('branch')->value() === 'primary',
                fn (Builder $query) => $query->whereNull('branch_id')
            )
            ->when(
                $request->filled('branch') && $request->string('branch')->value() !== 'primary',
                fn (Builder $query) => $query->where('branch_id', $request->integer('branch'))
            )
            ->when($request->filled('service'), fn (Builder $query) => $query->where('verification_service_id', $request->integer('service')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')));
    }

    private function verifyResultBoard(Request $request, VerificationService $service)
    {
        if (! $request->user()->hasResultFetchAccess()) {
            return back()->withErrors(['result' => 'Result board verification is not enabled for your account.']);
        }

        $validated = $request->validate([
            'branch_id' => 'nullable|integer',
        ]);

        $branch = null;

        if (! empty($validated['branch_id'])) {
            $branch = $request->user()->branches()
                ->where('is_active', true)
                ->whereKey($validated['branch_id'])
                ->with('wallet')
                ->firstOrFail();
        }

        if (!$service->is_active) {
            return back()->withErrors(['result' => 'This service is not available.']);
        }

        $price = $request->user()->getPriceForService($service);
        $walletBalance = $branch?->wallet?->total_balance ?? $request->user()->wallet?->total_balance ?? 0;

        if ($walletBalance < $price) {
            return back()->withErrors(['result' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $params = $request->except(['_token', 'branch_id']);
        $board = $this->boardFromService($service);

        $result = $this->resultVerificationEngine->verify(
            user: $request->user(),
            board: $board,
            params: $params,
            source: 'web',
            ipAddress: $request->ip(),
            branch: $branch,
        );

        if ($result->isSuccessful()) {
            $verification = VerificationRequest::query()
                ->where('user_id', $request->user()->id)
                ->where('verification_service_id', $service->id)
                ->latest('id')
                ->first();

            return Inertia::render('Customer/Verification/Result', [
                'service' => tap($service, fn (VerificationService $service) => $this->applyResultBoardDisplayName($service)),
                'result' => $result->toArray(),
                'searchParameter' => $verification?->search_parameter ?? $this->resultSearchParameter($board, $params),
                'verification' => $verification,
            ]);
        }

        return back()->withErrors(['result' => $result->getErrorMessage() ?? 'Result verification failed. Please try again.']);
    }

    private function isResultBoardFetchService(VerificationService $service): bool
    {
        return (bool) preg_match('/^[a-z0-9-]+-result-fetch$/', $service->slug);
    }

    private function boardFromService(VerificationService $service): string
    {
        return preg_replace('/-result-fetch$/', '', $service->slug);
    }

    private function applyResultBoardDisplayName(VerificationService $service): void
    {
        if (!$this->isResultBoardFetchService($service)) {
            return;
        }

        $board = strtoupper($this->boardFromService($service));
        $service->name = "{$board} Result Verification";
        $service->description = "Check {$board} result using the board result checker details.";
    }

    private function resultSearchParameter(string $board, array $params): string
    {
        return match ($board) {
            'waec' => trim((string) ($params['txtExamNumber'] ?? $params['ExamNumber'] ?? '')),
            'neco' => trim((string) ($params['reg_no'] ?? $params['exam_number'] ?? '')),
            'nbais' => trim((string) ($params['exam_no'] ?? $params['exam_number'] ?? '')),
            'nabteb' => trim((string) ($params['candid'] ?? $params['candidate_number'] ?? '')),
            default => '',
        };
    }
}
