<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function index(Request $request)
    {
        $verifications = $this->filteredVerificationsQuery($request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => VerificationRequest::count(),
            'completed' => VerificationRequest::where('status', 'completed')->count(),
            'failed' => VerificationRequest::where('status', 'failed')->count(),
            'pending' => VerificationRequest::where('status', 'pending')->count(),
        ];

        return Inertia::render('Admin/Verifications/Index', [
            'verifications' => $verifications,
            'stats' => $stats,
            'filters' => $request->only(['search', 'service', 'status', 'source', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->filteredVerificationsQuery($request)->orderByDesc('id');

        return CsvExport::download(
            filename: 'admin-verifications-'.now()->format('Ymd-His').'.csv',
            headers: ['Reference', 'Customer Name', 'Customer Email', 'Service', 'Provider', 'Search Parameter', 'Status', 'Amount Charged', 'Source', 'Created At', 'Completed At'],
            rows: function () use ($query) {
                foreach ($query->lazyByIdDesc(500, 'id') as $verification) {
                    yield [
                        $verification->reference,
                        $verification->user?->name,
                        $verification->user?->email,
                        $verification->verificationService?->name,
                        $verification->serviceProvider?->name,
                        $verification->search_parameter,
                        $verification->status,
                        $verification->amount_charged,
                        $verification->source,
                        $verification->created_at,
                        $verification->completed_at,
                    ];
                }
            },
        );
    }

    public function show(VerificationRequest $verification)
    {
        $verification->load(['user', 'verificationService', 'serviceProvider', 'transaction']);

        return Inertia::render('Admin/Verifications/Show', [
            'verification' => $verification,
        ]);
    }

    private function filteredVerificationsQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));

        return VerificationRequest::query()
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
                'created_at',
                'completed_at',
            ])
            ->with([
                'user:id,name,email',
                'verificationService:id,name',
                'serviceProvider:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery->where('reference', 'like', "%{$search}%")
                        ->orWhere('search_parameter', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('service'), fn (Builder $query) => $query->where('verification_service_id', $request->integer('service')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('source'), fn (Builder $query) => $query->where('source', $request->string('source')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')));
    }
}
