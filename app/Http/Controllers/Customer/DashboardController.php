<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\VerificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $wallet = $user->wallet;

        // Get verification counts by status
        $verificationsByStatus = $user->verificationRequests()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // This month's stats
        $thisMonthVerifications = $user->verificationRequests()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);

        $thisMonthByStatus = (clone $thisMonthVerifications)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Sum spent from completed verifications only
        $thisMonthSpent = $user->verificationRequests()
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount_charged');

        // Total spent all time (completed only)
        $totalSpent = $user->verificationRequests()
            ->where('status', 'completed')
            ->sum('amount_charged');

        // Pending includes both 'pending' and 'processing' statuses
        $pendingCount = ($verificationsByStatus['pending'] ?? 0) + ($verificationsByStatus['processing'] ?? 0);

        $stats = [
            'wallet_balance' => $wallet?->balance ?? 0,
            'bonus_balance' => $wallet?->bonus_balance ?? 0,
            'total_verifications' => $user->verificationRequests()->count(),
            'successful_verifications' => $verificationsByStatus['completed'] ?? 0,
            'failed_verifications' => $verificationsByStatus['failed'] ?? 0,
            'pending_verifications' => $pendingCount,
            'this_month_verifications' => array_sum($thisMonthByStatus),
            'this_month_completed' => $thisMonthByStatus['completed'] ?? 0,
            'this_month_failed' => $thisMonthByStatus['failed'] ?? 0,
            'this_month_spent' => $thisMonthSpent,
            'total_spent' => $totalSpent,
        ];

        // Verification counts by status (for display)
        $verificationCounts = [
            'all' => $user->verificationRequests()->count(),
            'completed' => $verificationsByStatus['completed'] ?? 0,
            'failed' => $verificationsByStatus['failed'] ?? 0,
            'pending' => $pendingCount,
        ];

        $recentVerifications = $user->verificationRequests()
            ->with('verificationService')
            ->latest()
            ->take(5)
            ->get();

        $recentTransactions = $user->transactions()
            ->latest()
            ->take(5)
            ->get();

        $services = VerificationService::active()->ordered()->get()
            ->map(function ($service) use ($user) {
                $service->price = $user->getPriceForService($service);
                return $service;
            });

        return Inertia::render('Customer/Dashboard', [
            'stats' => $stats,
            'verificationCounts' => $verificationCounts,
            'recentVerifications' => $recentVerifications,
            'recentTransactions' => $recentTransactions,
            'services' => $services,
        ]);
    }
}

