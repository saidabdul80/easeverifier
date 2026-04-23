<?php

namespace App\Http\Controllers\Admin;

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
        $transactions = $this->filteredTransactionsQuery($request)
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

    public function export(Request $request)
    {
        $query = $this->filteredTransactionsQuery($request)->orderByDesc('id');

        return CsvExport::download(
            filename: 'admin-transactions-'.now()->format('Ymd-His').'.csv',
            headers: ['Reference', 'Customer Name', 'Customer Email', 'Type', 'Category', 'Amount', 'Status', 'Description', 'Created At'],
            rows: function () use ($query) {
                foreach ($query->lazyByIdDesc(500, 'id') as $transaction) {
                    yield [
                        $transaction->reference,
                        $transaction->user?->name,
                        $transaction->user?->email,
                        $transaction->type,
                        $transaction->category,
                        $transaction->amount,
                        $transaction->status,
                        $transaction->description,
                        $transaction->created_at,
                    ];
                }
            },
        );
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['user', 'wallet']);

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    private function filteredTransactionsQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));

        return Transaction::query()
            ->select([
                'id',
                'user_id',
                'reference',
                'type',
                'category',
                'amount',
                'description',
                'status',
                'created_at',
            ])
            ->with('user:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')));
    }
}
