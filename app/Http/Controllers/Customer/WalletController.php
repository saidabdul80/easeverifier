<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallet = $request->user()->wallet;

        $transactions = $this->filteredTransactionsQuery($request)
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
                ->where('status','completed')
                ->sum('amount'),
            'total_spent' => $request->user()->transactions()
                ->where('type', 'debit')
                ->where('status','completed')
                ->sum('amount'),
        ];

        return Inertia::render('Customer/Wallet/Index', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'stats' => $stats,
            'filters' => $request->only(['type', 'category', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->filteredTransactionsQuery($request)->orderByDesc('id');

        return CsvExport::download(
            filename: 'wallet-transactions-'.now()->format('Ymd-His').'.csv',
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

    private function filteredTransactionsQuery(Request $request)
    {
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
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')));
    }
}
