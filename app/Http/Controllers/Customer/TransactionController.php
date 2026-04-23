<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $transactions = $this->filteredTransactionsQuery($request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total_credits' => $user->transactions()->where('type', 'credit')->where('status','=', 'completed')->where('category','!=', 'refund')->sum('amount'),
            'total_debits' => $user->transactions()->where('type', 'debit')->where('status','=', 'completed')->sum('amount'),
            'this_month_credits' => $user->transactions()
                ->where('category','!=', 'refund')
                ->where('type', 'credit')
                ->where('status','=', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
            'this_month_debits' => $user->transactions()
                ->where('type', 'debit')
                ->where('status','=', 'completed')
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

    public function export(Request $request)
    {
        $query = $this->filteredTransactionsQuery($request)->orderByDesc('id');

        return CsvExport::download(
            filename: 'customer-transactions-'.now()->format('Ymd-His').'.csv',
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

    public function show(Request $request, $transactionId)
    {
        $user = $request->user();

        // Get transaction scoped to the current user (prevents access to other users' transactions)
        $transaction = $user->transactions()
            ->with(['verificationRequest.verificationService'])
            ->findOrFail($transactionId);

        return Inertia::render('Customer/Transactions/Show', [
            'transaction' => $transaction,
            'user' => $user,
        ]);
    }

    private function filteredTransactionsQuery(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

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
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery->where('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))
            ->when($request->filled('min_amount'), fn (Builder $query) => $query->where('amount', '>=', (float) $request->input('min_amount')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')));
    }
}
