<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Models\Wallet;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Get transaction IDs for completed verifications (for accurate revenue)
        $completedTransactionIds = VerificationRequest::where('status', 'completed')
            ->whereNotNull('transaction_id')
            ->pluck('transaction_id');

        $todayCompletedTransactionIds = VerificationRequest::where('status', 'completed')
            ->whereNotNull('transaction_id')
            ->whereDate('created_at', today())
            ->pluck('transaction_id');

        $stats = [
            'total_customers' => User::role('customer')->count(),
            'active_customers' => User::role('customer')->where('is_active', true)->count(),
            'total_services' => VerificationService::count(),
            'active_services' => VerificationService::where('is_active', true)->count(),
            'total_verifications' => VerificationRequest::count(),
            'successful_verifications' => VerificationRequest::where('status', 'completed')->count(),
            'failed_verifications' => VerificationRequest::where('status', 'failed')->count(),
            'pending_verifications' => VerificationRequest::whereIn('status', ['pending', 'processing'])->count(),
            'total_revenue' => Transaction::whereIn('id', $completedTransactionIds)
                ->where('type', 'debit')
                ->sum('amount'),
            'total_wallet_balance' => Wallet::sum('balance'),
            'today_verifications' => VerificationRequest::whereDate('created_at', today())->count(),
            'today_revenue' => Transaction::whereIn('id', $todayCompletedTransactionIds)
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
            ->selectRaw('MONTH(verification_requests.created_at) as month, YEAR(verification_requests.created_at) as year, SUM(transactions.amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentVerifications' => $recentVerifications,
            'recentTransactions' => $recentTransactions,
            'monthlyRevenue' => $monthlyRevenue,
        ]);
    }
}

