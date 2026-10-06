<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaygoVerificationIntent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $paygoData = null;
        $loadPaygoData = function () use ($request, &$paygoData): array {
            return $paygoData ??= $this->paygoDashboardData($request);
        };

        return Inertia::render('Admin/Dashboard', [
            'stats' => fn () => $this->platformStats(),
            'recentVerifications' => fn () => $this->recentVerifications(),
            'monthlyRevenue' => fn () => $this->monthlyRevenue(),
            'platformTrend' => fn () => $this->platformTrend(),
            'paygoStats' => fn () => $loadPaygoData()['stats'],
            'paygoTrend' => fn () => $loadPaygoData()['trend'],
            'recentPaygo' => fn () => $loadPaygoData()['recent'],
            'paygoFilters' => $request->only(['paygo_customer', 'paygo_status', 'paygo_package', 'paygo_date_from', 'paygo_date_to']),
            'customerOptions' => fn () => User::role('customer')
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $customer) => [
                    'title' => $customer->name.' ('.$customer->email.')',
                    'value' => $customer->id,
                ]),
        ]);
    }

    private function platformStats(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $customerStats = User::role('customer')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN users.is_active = 1 THEN 1 ELSE 0 END) as active')
            ->first();
        $serviceStats = VerificationService::query()
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->first();
        $verificationStats = VerificationRequest::query()
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as today", [$todayStart, $todayEnd])
            ->first();

        return [
            'total_customers' => (int) ($customerStats?->total ?? 0),
            'active_customers' => (int) ($customerStats?->active ?? 0),
            'total_services' => (int) ($serviceStats?->total ?? 0),
            'active_services' => (int) ($serviceStats?->active ?? 0),
            'total_verifications' => (int) ($verificationStats?->total ?? 0),
            'successful_verifications' => (int) ($verificationStats?->successful ?? 0),
            'failed_verifications' => (int) ($verificationStats?->failed ?? 0),
            'pending_verifications' => (int) ($verificationStats?->pending ?? 0),
            'total_revenue' => (float) Transaction::whereIn('id', $this->completedVerificationTransactionIdsQuery())
                ->where('type', 'debit')
                ->sum('amount'),
            'total_wallet_balance' => (float) Wallet::sum('balance'),
            'today_verifications' => (int) ($verificationStats?->today ?? 0),
            'today_revenue' => (float) Transaction::whereIn(
                'id',
                $this->completedVerificationTransactionIdsQuery()->whereBetween('created_at', [$todayStart, $todayEnd])
            )->where('type', 'debit')->sum('amount'),
        ];
    }

    private function platformTrend()
    {
        $trendStart = now()->subDays(6)->startOfDay();
        $trendEnd = now()->endOfDay();
        $dates = collect(range(0, 6))->map(fn (int $offset) => $trendStart->copy()->addDays($offset));
        $dateExpression = $this->dailyDateExpression('created_at');
        $customerSignups = User::role('customer')
            ->whereBetween('created_at', [$trendStart, $trendEnd])
            ->selectRaw("{$dateExpression} as activity_date, COUNT(*) as aggregate")
            ->groupByRaw($dateExpression)
            ->pluck('aggregate', 'activity_date');
        $customerRunningTotal = User::role('customer')->where('created_at', '<', $trendStart)->count();
        $dailyVerifications = VerificationRequest::whereBetween('created_at', [$trendStart, $trendEnd])
            ->selectRaw("{$dateExpression} as activity_date, COUNT(*) as aggregate")
            ->groupByRaw($dateExpression)
            ->pluck('aggregate', 'activity_date');
        $verificationDateExpression = $this->dailyDateExpression('verification_requests.created_at');
        $dailyVerificationRevenue = VerificationRequest::where('verification_requests.status', 'completed')
            ->whereBetween('verification_requests.created_at', [$trendStart, $trendEnd])
            ->whereNotNull('verification_requests.transaction_id')
            ->join('transactions', 'verification_requests.transaction_id', '=', 'transactions.id')
            ->where('transactions.type', 'debit')
            ->selectRaw("{$verificationDateExpression} as activity_date, SUM(transactions.amount) as aggregate")
            ->groupByRaw($verificationDateExpression)
            ->pluck('aggregate', 'activity_date');
        $dailyWalletActivity = Transaction::where('status', 'completed')
            ->whereBetween('created_at', [$trendStart, $trendEnd])
            ->selectRaw("{$dateExpression} as activity_date, SUM(amount) as aggregate")
            ->groupByRaw($dateExpression)
            ->pluck('aggregate', 'activity_date');

        return $dates->map(function (Carbon $date) use (&$customerRunningTotal, $customerSignups, $dailyVerifications, $dailyVerificationRevenue, $dailyWalletActivity) {
            $key = $date->toDateString();
            $customerRunningTotal += (int) ($customerSignups[$key] ?? 0);

            return [
                'date' => $key,
                'label' => $date->format('M j'),
                'customers' => $customerRunningTotal,
                'verifications' => (int) ($dailyVerifications[$key] ?? 0),
                'verification_revenue' => (float) ($dailyVerificationRevenue[$key] ?? 0),
                'wallet_activity' => (float) ($dailyWalletActivity[$key] ?? 0),
            ];
        })->values();
    }

    private function recentVerifications()
    {
        return VerificationRequest::query()
            ->select(['id', 'user_id', 'verification_service_id', 'reference', 'status', 'created_at'])
            ->with(['user:id,name', 'verificationService:id,name'])
            ->latest('id')
            ->limit(4)
            ->get();
    }

    private function monthlyRevenue()
    {
        return VerificationRequest::where('verification_requests.status', 'completed')
            ->whereNotNull('verification_requests.transaction_id')
            ->where('verification_requests.created_at', '>=', now()->subMonths(6))
            ->join('transactions', 'verification_requests.transaction_id', '=', 'transactions.id')
            ->where('transactions.type', 'debit')
            ->selectRaw($this->monthlyRevenueSelect())
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }

    private function paygoDashboardData(Request $request): array
    {
        $filteredPaygo = $this->filteredPaygoQuery($request);
        $aggregate = (clone $filteredPaygo)
            ->selectRaw("COUNT(*) as total_payments,
                SUM(CASE WHEN status IN ('paid', 'verifying', 'used') THEN 1 ELSE 0 END) as successful_payments,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                SUM(CASE WHEN status IN ('failed', 'expired') THEN 1 ELSE 0 END) as failed_payments,
                COALESCE(SUM(CASE WHEN status IN ('paid', 'verifying', 'used') THEN amount ELSE 0 END), 0) as gross_revenue,
                COALESCE(SUM(CASE WHEN status IN ('paid', 'verifying', 'used') THEN system_price_snapshot ELSE 0 END), 0) as system_settlement,
                SUM(CASE WHEN status IN ('paid', 'verifying', 'used') AND flow_type = 'result_reference' THEN 1 ELSE 0 END) as reference_packages")
            ->first();
        $paygoTotal = (int) ($aggregate?->total_payments ?? 0);
        $paygoSuccessful = (int) ($aggregate?->successful_payments ?? 0);
        $grossRevenue = (float) ($aggregate?->gross_revenue ?? 0);
        $systemSettlement = (float) ($aggregate?->system_settlement ?? 0);
        $paidStatuses = ['paid', 'verifying', 'used'];

        $trendEnd = $request->filled('paygo_date_to')
            ? Carbon::parse($request->date('paygo_date_to'))->endOfDay()
            : now()->endOfDay();
        $trendStart = $trendEnd->copy()->subDays(6)->startOfDay();
        $dateExpression = $this->dailyDateExpression('created_at');
        $trendPayments = (clone $filteredPaygo)
            ->whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$trendStart, $trendEnd])
            ->selectRaw("{$dateExpression} as activity_date, SUM(amount) as gross, SUM(system_price_snapshot) as settlement, COUNT(*) as payments")
            ->groupByRaw($dateExpression)
            ->get()
            ->keyBy('activity_date');
        $trend = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $trendPayments) {
            $date = $trendStart->copy()->addDays($offset);
            $payments = $trendPayments[$date->toDateString()] ?? null;
            $gross = (float) ($payments?->gross ?? 0);
            $settlement = (float) ($payments?->settlement ?? 0);

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'gross' => $gross,
                'settlement' => $settlement,
                'earnings' => max(0, $gross - $settlement),
                'payments' => (int) ($payments?->payments ?? 0),
            ];
        })->values();
        $recent = (clone $filteredPaygo)
            ->select([
                'id',
                'user_id',
                'customer_paygo_service_id',
                'verification_service_id',
                'reference',
                'flow_type',
                'amount',
                'system_price_snapshot',
                'status',
                'created_at',
            ])
            ->with(['user:id,name,email', 'paygoService:id,name', 'verificationService:id,name,slug'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (PaygoVerificationIntent $intent) => [
                'id' => $intent->id,
                'reference' => $intent->reference,
                'customer_name' => $intent->user?->name,
                'customer_email' => $intent->user?->email,
                'service_name' => $intent->paygoService?->name ?? $intent->verificationService?->name,
                'package_type' => $intent->isResultReferenceFlow() ? 'reference' : 'normal',
                'amount' => (float) $intent->amount,
                'system_price' => (float) $intent->system_price_snapshot,
                'customer_earning' => max(0, (float) $intent->amount - (float) $intent->system_price_snapshot),
                'status' => $intent->status,
                'created_at' => $intent->created_at,
            ]);

        return [
            'stats' => [
                'total_payments' => $paygoTotal,
                'successful_payments' => $paygoSuccessful,
                'pending_payments' => (int) ($aggregate?->pending_payments ?? 0),
                'failed_payments' => (int) ($aggregate?->failed_payments ?? 0),
                'gross_revenue' => $grossRevenue,
                'system_settlement' => $systemSettlement,
                'customer_earnings' => max(0, $grossRevenue - $systemSettlement),
                'reference_packages' => (int) ($aggregate?->reference_packages ?? 0),
                'conversion_rate' => $paygoTotal > 0 ? round(($paygoSuccessful / $paygoTotal) * 100, 1) : 0,
            ],
            'trend' => $trend,
            'recent' => $recent,
        ];
    }

    private function filteredPaygoQuery(Request $request): Builder
    {
        return PaygoVerificationIntent::query()
            ->when($request->filled('paygo_customer'), fn (Builder $query) => $query->where('user_id', $request->integer('paygo_customer')))
            ->when($request->filled('paygo_status'), fn (Builder $query) => $query->where('status', $request->string('paygo_status')))
            ->when($request->string('paygo_package')->value() === 'reference', fn (Builder $query) => $query->where('flow_type', 'result_reference'))
            ->when($request->string('paygo_package')->value() === 'normal', fn (Builder $query) => $query->where('flow_type', '!=', 'result_reference'))
            ->when($request->filled('paygo_date_from'), fn (Builder $query) => $query->where('created_at', '>=', $request->date('paygo_date_from')->startOfDay()))
            ->when($request->filled('paygo_date_to'), fn (Builder $query) => $query->where('created_at', '<=', $request->date('paygo_date_to')->endOfDay()));
    }

    private function completedVerificationTransactionIdsQuery(): Builder
    {
        return VerificationRequest::query()
            ->select('transaction_id')
            ->where('status', 'completed')
            ->whereNotNull('transaction_id');
    }

    private function monthlyRevenueSelect(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', verification_requests.created_at) AS INTEGER) as month, CAST(strftime('%Y', verification_requests.created_at) AS INTEGER) as year, SUM(transactions.amount) as total",
            default => 'MONTH(verification_requests.created_at) as month, YEAR(verification_requests.created_at) as year, SUM(transactions.amount) as total',
        };
    }

    private function dailyDateExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "date({$column})",
            default => "DATE({$column})",
        };
    }
}
