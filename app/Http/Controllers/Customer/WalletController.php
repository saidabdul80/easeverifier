<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallet = $request->user()->wallet;

        $transactions = $request->user()->transactions()
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->category, fn($q, $cat) => $q->where('category', $cat))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'balance' => $wallet?->balance ?? 0,
            'bonus_balance' => $wallet?->bonus_balance ?? 0,
            'total_balance' => $wallet?->total_balance ?? 0,
            'total_funded' => $request->user()->transactions()
                ->where('type', 'credit')
                ->where('category', 'funding')
                ->sum('amount'),
            'total_spent' => $request->user()->transactions()
                ->where('type', 'debit')
                ->sum('amount'),
        ];

        return Inertia::render('Customer/Wallet/Index', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'stats' => $stats,
            'filters' => $request->only(['type', 'category', 'date_from', 'date_to']),
        ]);
    }

    public function fund(Request $request)
    {
        // This would integrate with payment gateway
        // For now, just show the funding page
        return Inertia::render('Customer/Wallet/Fund', [
            'wallet' => $request->user()->wallet,
        ]);
    }

    public function processFunding(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        // This would process payment through payment gateway
        // For demo, we'll just redirect back
        // In production, this would redirect to payment gateway

        return back()->with('info', 'Payment gateway integration pending.');
    }

    public function showTransaction(Transaction $transaction, Request $request)
    {
        // Ensure user can only see their own transactions
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        return Inertia::render('Customer/Wallet/Transaction', [
            'transaction' => $transaction,
        ]);
    }
}

