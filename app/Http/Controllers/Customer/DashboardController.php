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

        // Get completed verification IDs for this month
        $completedVerificationIds = $user->verificationRequests()
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->pluck('transaction_id')
            ->filter(); // Remove nulls

        // Sum from transactions linked to completed verifications
        $thisMonthSpent = $user->verificationRequests()->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount_charged');

        $stats = [
            'wallet_balance' => $wallet?->balance ?? 0,
            'bonus_balance' => $wallet?->bonus_balance ?? 0,
            'total_verifications' => $user->verificationRequests()->count(),
            'successful_verifications' => $user->verificationRequests()->where('status', 'completed')->count(),
            'failed_verifications' => $user->verificationRequests()->where('status', 'failed')->count(),
            'this_month_verifications' => $user->verificationRequests()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'this_month_spent' => $thisMonthSpent,
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
            'recentVerifications' => $recentVerifications,
            'recentTransactions' => $recentTransactions,
            'services' => $services,
        ]);
    }
}

