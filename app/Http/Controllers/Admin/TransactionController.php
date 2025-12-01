<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = Transaction::with('user')
            ->when($request->search, function ($query, $search) {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($q) => 
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                    );
            })
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->category, fn($q, $cat) => $q->where('category', $cat))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total_credits' => Transaction::where('type', 'credit')->where('category','!=', 'refund')->sum('amount'),
            'total_debits' => Transaction::where('type', 'debit')->sum('amount'),
            'today_credits' => Transaction::where('type', 'credit')->where('category','!=', 'refund')->whereDate('created_at', today())->sum('amount'),
            'today_debits' => Transaction::where('type', 'debit')->whereDate('created_at', today())->sum('amount'),
        ];

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'filters' => $request->only(['search', 'type', 'category', 'date_from', 'date_to']),
        ]);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['user', 'wallet']);

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => $transaction,
        ]);
    }
}

