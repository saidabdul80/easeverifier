<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PaygoVerificationIntent;
use App\Models\Transaction;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $transactions = $this->filteredTransactionsQuery($request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $paygoIntents = $this->filteredPaygoIntentsQuery($request)
            ->with(['paygoService:id,name', 'verificationService:id,name,slug'])
            ->latest('id')
            ->paginate(20, ['*'], 'paygo_page')
            ->withQueryString()
            ->through(fn (PaygoVerificationIntent $intent) => [
                'id' => $intent->id,
                'reference' => $intent->reference,
                'service_name' => $intent->paygoService?->name,
                'board' => $intent->isResultFlow()
                    ? strtoupper((string) preg_replace('/-result-fetch$/', '', $intent->verificationService?->slug ?? ''))
                    : null,
                'flow_type' => $intent->flow_type,
                'package_type' => $intent->isResultReferenceFlow() ? 'reference' : 'normal',
                'amount' => (float) $intent->amount,
                'system_price' => (float) $intent->system_price_snapshot,
                'earning' => max(0, (float) $intent->amount - (float) $intent->system_price_snapshot),
                'lookup_label' => $intent->lookup_label,
                'status' => $intent->status,
                'attempts_used' => $intent->isResultReferenceFlow() ? $intent->reference_fetches : $intent->verification_attempts,
                'attempts_allowed' => $intent->max_fetches_snapshot,
                'paid_at' => $intent->paid_at,
                'created_at' => $intent->created_at,
            ]);

        $stats = [
            'total_credits' => $user->transactions()->where('type', 'credit')->where('status', '=', 'completed')->where('category', '!=', 'refund')->sum('amount'),
            'total_debits' => $user->transactions()->where('type', 'debit')->where('status', '=', 'completed')->sum('amount'),
            'this_month_credits' => $user->transactions()
                ->where('category', '!=', 'refund')
                ->where('type', 'credit')
                ->where('status', '=', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
            'this_month_debits' => $user->transactions()
                ->where('type', 'debit')
                ->where('status', '=', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
        ];

        $paidStatuses = ['paid', 'verifying', 'used'];
        $paidPaygo = PaygoVerificationIntent::query()
            ->where('user_id', $user->id)
            ->whereIn('status', $paidStatuses);
        $paidPaygoThisMonth = (clone $paidPaygo)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $paygoGrossRevenue = (float) (clone $paidPaygo)->sum('amount');
        $paygoSystemSettlement = (float) (clone $paidPaygo)->sum('system_price_snapshot');
        $paygoMonthRevenue = (float) (clone $paidPaygoThisMonth)->sum('amount');
        $paygoMonthSettlement = (float) (clone $paidPaygoThisMonth)->sum('system_price_snapshot');

        $paygoStats = [
            'gross_revenue' => $paygoGrossRevenue,
            'system_settlement' => $paygoSystemSettlement,
            'net_earnings' => max(0, $paygoGrossRevenue - $paygoSystemSettlement),
            'successful_payments' => (int) (clone $paidPaygo)->count(),
            'reference_packages' => (int) (clone $paidPaygo)->where('flow_type', 'result_reference')->count(),
            'this_month_revenue' => $paygoMonthRevenue,
            'this_month_earnings' => max(0, $paygoMonthRevenue - $paygoMonthSettlement),
        ];

        return Inertia::render('Customer/Transactions/Index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'paygoIntents' => $paygoIntents,
            'paygoStats' => $paygoStats,
            'filters' => $request->only(['search', 'type', 'category', 'min_amount', 'date_from', 'date_to']),
            'paygoFilters' => $request->only(['paygo_search', 'paygo_status', 'paygo_package']),
            'activeTab' => $request->string('tab')->value() === 'paygo' ? 'paygo' : 'wallet',
        ]);
    }

    public function export(Request $request)
    {
        if ($request->string('tab')->value() === 'paygo') {
            $query = $this->filteredPaygoIntentsQuery($request)
                ->with(['paygoService:id,name', 'verificationService:id,name,slug'])
                ->orderByDesc('id');

            return CsvExport::download(
                filename: 'customer-paygo-transactions-'.now()->format('Ymd-His').'.csv',
                headers: ['Reference', 'Service', 'Package', 'Amount', 'EaseVerifier Settlement', 'Earning', 'Lookup', 'Attempts Used', 'Attempts Allowed', 'Status', 'Paid At', 'Created At'],
                rows: function () use ($query) {
                    foreach ($query->lazyByIdDesc(500, 'id') as $intent) {
                        yield [
                            $intent->reference,
                            $intent->paygoService?->name,
                            $intent->isResultReferenceFlow() ? 'Reference package' : 'Normal payment',
                            $intent->amount,
                            $intent->system_price_snapshot,
                            max(0, (float) $intent->amount - (float) $intent->system_price_snapshot),
                            $intent->lookup_label,
                            $intent->isResultReferenceFlow() ? $intent->reference_fetches : $intent->verification_attempts,
                            $intent->max_fetches_snapshot,
                            $intent->status,
                            $intent->paid_at,
                            $intent->created_at,
                        ];
                    }
                },
            );
        }

        $query = $this->filteredTransactionsQuery($request)->orderByDesc('id');

        return CsvExport::download(
            filename: 'customer-transactions-'.now()->format('Ymd-His').'.csv',
            headers: ['Reference', 'Type', 'Category', 'Amount', 'Balance After', 'Status', 'Description', 'Created At'],
            rows: function () use ($query) {
                foreach ($query->lazyByIdDesc(500, 'id') as $transaction) {
                    yield [
                        $transaction->reference,
                        $transaction->type,
                        $transaction->category,
                        $transaction->amount,
                        $transaction->balance_after,
                        $transaction->status,
                        $transaction->description,
                        $transaction->created_at,
                    ];
                }
            },
        );
    }

    public function show(Request $request, $transactionId)
    {
        $user = $request->user();

        // Get transaction scoped to the current user (prevents access to other users' transactions)
        $transaction = $user->transactions()
            ->with(['verificationRequest.verificationService'])
            ->findOrFail($transactionId);

        return Inertia::render('Customer/Transactions/Show', [
            'transaction' => $transaction,
            'user' => $user,
        ]);
    }

    private function filteredTransactionsQuery(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        return $request->user()->transactions()
            ->select([
                'id',
                'user_id',
                'reference',
                'type',
                'category',
                'amount',
                'balance_after',
                'description',
                'status',
                'created_at',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery->where('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))
            ->when($request->filled('min_amount'), fn (Builder $query) => $query->where('amount', '>=', (float) $request->input('min_amount')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')));
    }

    private function filteredPaygoIntentsQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('paygo_search', ''));

        return PaygoVerificationIntent::query()
            ->where('user_id', $request->user()->id)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery->where('reference', 'like', "%{$search}%")
                        ->orWhere('lookup_label', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('paygo_status'), fn (Builder $query) => $query->where('status', $request->string('paygo_status')))
            ->when($request->string('paygo_package')->value() === 'reference', fn (Builder $query) => $query->where('flow_type', 'result_reference'))
            ->when($request->string('paygo_package')->value() === 'normal', fn (Builder $query) => $query->where('flow_type', '!=', 'result_reference'));
    }
}
