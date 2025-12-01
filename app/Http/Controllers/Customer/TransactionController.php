<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $transactions = $user->transactions()
            ->when($request->search, function ($query, $search) {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->category, fn($q, $cat) => $q->where('category', $cat))
            ->when($request->min_amount, fn($q, $amount) => $q->where('amount', '>=', $amount))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total_credits' => $user->transactions()->where('type', 'credit')->sum('amount'),
            'total_debits' => $user->transactions()->where('type', 'debit')->sum('amount'),
            'this_month_credits' => $user->transactions()
                ->where('type', 'credit')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
            'this_month_debits' => $user->transactions()
                ->where('type', 'debit')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
        ];

        return Inertia::render('Customer/Transactions/Index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'filters' => $request->only(['search', 'type', 'category', 'min_amount', 'date_from', 'date_to']),
        ]);
    }

    public function show(Transaction $transaction, Request $request)
    {
        // Ensure user can only see their own transactions
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        // Load related verification request if exists
        $transaction->load(['verificationRequest.verificationService']);

        return Inertia::render('Customer/Transactions/Show', [
            'transaction' => $transaction,
            'user' => $request->user(),
        ]);
    }
}

