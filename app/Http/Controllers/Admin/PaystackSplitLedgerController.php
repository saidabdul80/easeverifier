<?php

namespace App\Http\Controllers\Admin;

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
            ->with([
                'user:id,name,email',
                'customer:id,user_id,company_name,referral_code',
                'paygoIntent:id,reference,customer_paygo_service_id,amount,status,paid_at',
                'paygoIntent.paygoService:id,name,public_slug',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('payment_reference', 'like', "%{$search}%")
                        ->orWhere('split_reference', 'like', "%{$search}%")
                        ->orWhere('subaccount_code', 'like', "%{$search}%")
                        ->orWhere('subaccount_label', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                            $customerQuery
                                ->where('company_name', 'like', "%{$search}%")
                                ->orWhere('referral_code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('paid_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('paid_at', '<=', $request->date('date_to')));

        return Inertia::render('Admin/PaystackSplits/Index', [
            'filters' => $request->only(['search', 'date_from', 'date_to']),
            'stats' => [
                'total_amount' => (float) (clone $query)->sum('flat_amount'),
                'total_entries' => (int) (clone $query)->count(),
                'today_amount' => (float) CustomerPaystackSplitLedger::whereDate('paid_at', today())->sum('flat_amount'),
            ],
            'ledgers' => $query
                ->latest('paid_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (CustomerPaystackSplitLedger $ledger) => $this->ledgerPayload($ledger)),
        ]);
    }

    private function ledgerPayload(CustomerPaystackSplitLedger $ledger): array
    {
        return [
            'id' => $ledger->id,
            'customer_name' => $ledger->customer?->company_name ?: $ledger->user?->name,
            'customer_email' => $ledger->user?->email,
            'referral_code' => $ledger->customer?->referral_code,
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
        ];
    }
}
