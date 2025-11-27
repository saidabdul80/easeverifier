<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_customers' => User::role('customer')->count(),
            'active_customers' => User::role('customer')->where('is_active', true)->count(),
            'total_services' => VerificationService::count(),
            'active_services' => VerificationService::where('is_active', true)->count(),
            'total_verifications' => VerificationRequest::count(),
            'successful_verifications' => VerificationRequest::where('status', 'completed')->count(),
            'failed_verifications' => VerificationRequest::where('status', 'failed')->count(),
            'total_revenue' => Transaction::where('type', 'debit')
                ->where('category', 'verification')
                ->sum('amount'),
            'total_wallet_balance' => Wallet::sum('balance'),
            'today_verifications' => VerificationRequest::whereDate('created_at', today())->count(),
            'today_revenue' => Transaction::where('type', 'debit')
                ->where('category', 'verification')
                ->whereDate('created_at', today())
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

        // Monthly revenue chart data
        $monthlyRevenue = Transaction::where('type', 'debit')
            ->where('category', 'verification')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(amount) as total')
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

