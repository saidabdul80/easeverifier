<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallets = Wallet::with('user')
            ->when($request->search, function ($query, $search) {
                $query->whereHas('user', fn($q) => 
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                );
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $totalBalance = Wallet::sum('balance');
        $totalBonusBalance = Wallet::sum('bonus_balance');

        return Inertia::render('Admin/Wallets/Index', [
            'wallets' => $wallets,
            'totalBalance' => $totalBalance,
            'totalBonusBalance' => $totalBonusBalance,
            'filters' => $request->only(['search']),
        ]);
    }

    public function credit(Request $request, User $customer)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'is_bonus' => 'boolean',
        ]);

        $wallet = $customer->wallet;

        if (!$wallet) {
            return back()->with('error', 'Customer does not have a wallet.');
        }

        if ($validated['is_bonus'] ?? false) {
            $wallet->update([
                'bonus_balance' => $wallet->bonus_balance + $validated['amount']
            ]);

            $wallet->transactions()->create([
                'user_id' => $customer->id,
                'reference' => \App\Models\Transaction::generateReference(),
                'type' => 'credit',
                'category' => 'bonus',
                'amount' => $validated['amount'],
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance,
                'description' => $validated['description'],
                'metadata' => ['credited_by' => auth()->id()],
                'status' => 'completed',
            ]);
        } else {
            $wallet->credit(
                $validated['amount'],
                'funding',
                $validated['description'],
                ['credited_by' => auth()->id()]
            );
        }

        return back()->with('success', 'Wallet credited successfully.');
    }

    public function debit(Request $request, User $customer)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
        ]);

        $wallet = $customer->wallet;

        if (!$wallet) {
            return back()->with('error', 'Customer does not have a wallet.');
        }

        if (!$wallet->hasSufficientFunds($validated['amount'])) {
            return back()->with('error', 'Insufficient wallet balance.');
        }

        $wallet->debit(
            $validated['amount'],
            'adjustment',
            $validated['description'],
            ['debited_by' => auth()->id()]
        );

        return back()->with('success', 'Wallet debited successfully.');
    }
}

