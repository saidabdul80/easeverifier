<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Wallet;
use App\Services\WalletTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function __construct(
        protected WalletTransferService $walletTransferService,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $branches = $user->branches()
            ->with([
                'wallet',
                'apiKeys' => fn ($query) => $query->latest(),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'contact_email' => $branch->contact_email,
                'contact_phone' => $branch->contact_phone,
                'address' => $branch->address,
                'is_active' => $branch->is_active,
                'wallet' => $branch->wallet ? [
                    'id' => $branch->wallet->id,
                    'balance' => (float) $branch->wallet->balance,
                    'bonus_balance' => (float) $branch->wallet->bonus_balance,
                    'total_balance' => (float) $branch->wallet->total_balance,
                    'currency' => $branch->wallet->currency,
                ] : null,
                'api_keys' => $branch->apiKeys->map(fn ($key) => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key' => $key->key,
                    'environment' => $key->environment,
                    'is_active' => $key->is_active,
                    'last_used_at' => $key->last_used_at?->diffForHumans(),
                    'created_at' => $key->created_at->format('M d, Y'),
                ]),
            ]);

        return Inertia::render('Customer/Branches/Index', [
            'primaryWallet' => $user->wallet ? [
                'id' => $user->wallet->id,
                'balance' => (float) $user->wallet->balance,
                'bonus_balance' => (float) $user->wallet->bonus_balance,
                'total_balance' => (float) $user->wallet->total_balance,
                'currency' => $user->wallet->currency,
            ] : null,
            'branches' => $branches,
            'stats' => [
                'branch_count' => $branches->count(),
                'active_branch_count' => $branches->where('is_active', true)->count(),
                'total_branch_balance' => $branches->sum(fn ($branch) => $branch['wallet']['total_balance'] ?? 0),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')->where('user_id', $request->user()->id),
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->branches()->create($validated);

        return back()->with('success', 'Branch created successfully.');
    }

    public function update(Request $request, Branch $branch)
    {
        abort_unless($branch->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')
                    ->where('user_id', $request->user()->id)
                    ->ignore($branch->id),
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $branch->update($validated);

        return back()->with('success', 'Branch updated successfully.');
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'to_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $user = $request->user();
        $fromBranch = $validated['from_branch_id']
            ? $user->branches()->whereKey($validated['from_branch_id'])->firstOrFail()
            : null;
        $toBranch = $validated['to_branch_id']
            ? $user->branches()->whereKey($validated['to_branch_id'])->firstOrFail()
            : null;

        if (($fromBranch?->id ?? null) === ($toBranch?->id ?? null)) {
            return back()->withErrors(['amount' => 'Source and destination must be different.']);
        }

        $fromWallet = $fromBranch?->wallet ?? $user->wallet;
        $toWallet = $toBranch?->wallet ?? $user->wallet;

        if (! $fromWallet || ! $toWallet) {
            return back()->withErrors(['amount' => 'Unable to resolve the selected wallets.']);
        }

        $fromLabel = $fromBranch?->name ?? 'Primary wallet';
        $toLabel = $toBranch?->name ?? 'Primary wallet';

        $this->walletTransferService->transfer(
            $fromWallet,
            $toWallet,
            (float) $validated['amount'],
            sprintf('Wallet transfer: %s to %s', $fromLabel, $toLabel),
            [
                'from_branch_id' => $fromBranch?->id,
                'to_branch_id' => $toBranch?->id,
                'from_label' => $fromLabel,
                'to_label' => $toLabel,
            ],
        );

        return back()->with('success', 'Wallet transfer completed successfully.');
    }
}
