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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_customers' => User::role('customer')->count(),
            'active_customers' => User::role('customer')->where('is_active', true)->count(),
            'total_services' => VerificationService::count(),
            'active_services' => VerificationService::where('is_active', true)->count(),
            'total_verifications' => VerificationRequest::count(),
            'successful_verifications' => VerificationRequest::where('status', 'completed')->count(),
            'failed_verifications' => VerificationRequest::where('status', 'failed')->count(),
            'pending_verifications' => VerificationRequest::whereIn('status', ['pending', 'processing'])->count(),
            'total_revenue' => Transaction::whereIn('id', $this->completedVerificationTransactionIdsQuery())
                ->where('type', 'debit')
                ->sum('amount'),
            'total_wallet_balance' => Wallet::sum('balance'),
            'today_verifications' => VerificationRequest::whereDate('created_at', today())->count(),
            'today_revenue' => Transaction::whereIn('id', $this->completedVerificationTransactionIdsQuery()->whereDate('created_at', today()))
                ->where('type', 'debit')
                ->sum('amount'),
        ];

        $recentVerifications = VerificationRequest::with(['user', 'verificationService'])
            ->latest()
            ->take(10)
            ->get();

        $recentTransactions = Transaction::with('user')
            ->latest()
            ->take(10)
            ->get();

        // Monthly revenue chart data (based on completed verifications)
        $monthlyRevenue = VerificationRequest::where('verification_requests.status', 'completed')
            ->whereNotNull('verification_requests.transaction_id')
            ->where('verification_requests.created_at', '>=', now()->subMonths(6))
            ->join('transactions', 'verification_requests.transaction_id', '=', 'transactions.id')
            ->where('transactions.type', 'debit')
            ->selectRaw($this->monthlyRevenueSelect())
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $filteredPaygo = $this->filteredPaygoQuery($request);
        $paidStatuses = ['paid', 'verifying', 'used'];
        $paidPaygo = (clone $filteredPaygo)->whereIn('status', $paidStatuses);
        $paygoGrossRevenue = (float) (clone $paidPaygo)->sum('amount');
        $paygoSystemSettlement = (float) (clone $paidPaygo)->sum('system_price_snapshot');
        $paygoTotal = (int) (clone $filteredPaygo)->count();
        $paygoSuccessful = (int) (clone $paidPaygo)->count();

        $paygoStats = [
            'total_payments' => $paygoTotal,
            'successful_payments' => $paygoSuccessful,
            'pending_payments' => (int) (clone $filteredPaygo)->where('status', 'pending')->count(),
            'failed_payments' => (int) (clone $filteredPaygo)->whereIn('status', ['failed', 'expired'])->count(),
            'gross_revenue' => $paygoGrossRevenue,
            'system_settlement' => $paygoSystemSettlement,
            'customer_earnings' => max(0, $paygoGrossRevenue - $paygoSystemSettlement),
            'reference_packages' => (int) (clone $paidPaygo)->where('flow_type', 'result_reference')->count(),
            'conversion_rate' => $paygoTotal > 0 ? round(($paygoSuccessful / $paygoTotal) * 100, 1) : 0,
        ];

        $recentPaygo = (clone $filteredPaygo)
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

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentVerifications' => $recentVerifications,
            'recentTransactions' => $recentTransactions,
            'monthlyRevenue' => $monthlyRevenue,
            'paygoStats' => $paygoStats,
            'recentPaygo' => $recentPaygo,
            'paygoFilters' => $request->only(['paygo_customer', 'paygo_status', 'paygo_package', 'paygo_date_from', 'paygo_date_to']),
            'customerOptions' => User::role('customer')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $customer) => [
                    'title' => $customer->name.' ('.$customer->email.')',
                    'value' => $customer->id,
                ]),
        ]);
    }

    private function filteredPaygoQuery(Request $request): Builder
    {
        return PaygoVerificationIntent::query()
            ->when($request->filled('paygo_customer'), fn (Builder $query) => $query->where('user_id', $request->integer('paygo_customer')))
            ->when($request->filled('paygo_status'), fn (Builder $query) => $query->where('status', $request->string('paygo_status')))
            ->when($request->string('paygo_package')->value() === 'reference', fn (Builder $query) => $query->where('flow_type', 'result_reference'))
            ->when($request->string('paygo_package')->value() === 'normal', fn (Builder $query) => $query->where('flow_type', '!=', 'result_reference'))
            ->when($request->filled('paygo_date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('paygo_date_from')))
            ->when($request->filled('paygo_date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('paygo_date_to')));
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
}
