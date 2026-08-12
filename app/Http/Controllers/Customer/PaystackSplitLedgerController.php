<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerPaystackSplitLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaystackSplitLedgerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = CustomerPaystackSplitLedger::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'paygoIntent:id,reference,customer_paygo_service_id,amount,status,paid_at',
                'paygoIntent.paygoService:id,name,public_slug',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('payment_reference', 'like', "%{$search}%")
                        ->orWhere('split_reference', 'like', "%{$search}%")
                        ->orWhere('subaccount_code', 'like', "%{$search}%")
                        ->orWhere('subaccount_label', 'like', "%{$search}%");
                });
            });

        return Inertia::render('Customer/Paygo/Splits', [
            'filters' => $request->only(['search']),
            'stats' => [
                'total_amount' => (float) (clone $query)->sum('flat_amount'),
                'total_entries' => (int) (clone $query)->count(),
                'today_amount' => (float) CustomerPaystackSplitLedger::where('user_id', $request->user()->id)
                    ->whereDate('paid_at', today())
                    ->sum('flat_amount'),
            ],
            'ledgers' => $query
                ->latest('paid_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (CustomerPaystackSplitLedger $ledger) => [
                    'id' => $ledger->id,
                    'payment_reference' => $ledger->payment_reference,
                    'paygo_reference' => $ledger->paygoIntent?->reference,
                    'service_name' => $ledger->paygoIntent?->paygoService?->name,
                    'subaccount_label' => $ledger->subaccount_label,
                    'subaccount_code' => $ledger->subaccount_code,
                    'flat_amount' => (float) $ledger->flat_amount,
                    'transaction_amount' => (float) $ledger->transaction_amount,
                    'main_account_remainder' => (float) $ledger->main_account_remainder,
                    'status' => $ledger->status,
                    'paid_at' => $ledger->paid_at,
                    'created_at' => $ledger->created_at,
                ]),
        ]);
    }
}
