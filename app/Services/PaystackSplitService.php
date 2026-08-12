<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPaystackSplitAccount;
use RuntimeException;

class PaystackSplitService
{
    public function buildDynamicFlatSplit(?Customer $customer, int $amountInKobo, string $paymentReference): ?array
    {
        if (! $customer || $amountInKobo <= 0) {
            return null;
        }

        $accounts = $customer->paystackSplitAccounts()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        if ($accounts->count() > 2) {
            throw new RuntimeException('A customer can have at most two active Paystack split accounts.');
        }

        $subaccounts = $accounts
            ->map(fn (CustomerPaystackSplitAccount $account) => [
                'subaccount' => $account->subaccount_code,
                'share' => $this->amountToKobo((float) $account->flat_amount),
            ])
            ->filter(fn (array $account) => $account['share'] > 0)
            ->values();

        if ($subaccounts->isEmpty()) {
            return null;
        }

        $totalShare = (int) $subaccounts->sum('share');

        if ($totalShare < 100) {
            throw new RuntimeException('The active Paystack split total must be at least NGN 1.00.');
        }

        if ($totalShare >= $amountInKobo) {
            throw new RuntimeException('The configured Paystack split amount must be lower than the transaction amount.');
        }

        $splitReference = 'SPLIT-'.$paymentReference;
        $bearerType = $this->bearerTypeFor($subaccounts);
        $paymentOptions = $this->paymentOptionsFor($subaccounts, $amountInKobo, $splitReference, $bearerType);

        return [
            'payment_options' => $paymentOptions,
            'metadata' => [
                'applied' => true,
                'type' => 'flat',
                'bearer_type' => $bearerType,
                'reference' => $splitReference,
                'method' => $subaccounts->count() === 1 ? 'single_subaccount' : 'dynamic_split',
                'total_split_amount' => $totalShare / 100,
                'total_split_amount_kobo' => $totalShare,
                'main_account_remainder' => ($amountInKobo - $totalShare) / 100,
                'main_account_remainder_kobo' => $amountInKobo - $totalShare,
                'wallet_credit_skipped' => true,
                'subaccounts' => $accounts
                    ->map(fn (CustomerPaystackSplitAccount $account) => [
                        'id' => $account->id,
                        'label' => $account->label,
                        'subaccount_code' => $account->subaccount_code,
                        'share' => $this->amountToKobo((float) $account->flat_amount),
                        'flat_amount' => (float) $account->flat_amount,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function amountToKobo(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function bearerTypeFor($subaccounts): string
    {
        $hasSmallShare = $subaccounts->contains(fn (array $account) => (int) $account['share'] <= 5000);

        return $hasSmallShare ? 'account' : 'all-proportional';
    }

    private function paymentOptionsFor($subaccounts, int $amountInKobo, string $splitReference, string $bearerType): array
    {
        if ($subaccounts->count() === 1) {
            $subaccount = $subaccounts->first();

            return [
                'subaccount' => $subaccount['subaccount'],
                'transaction_charge' => $amountInKobo - (int) $subaccount['share'],
                'bearer' => 'account',
                'metadata' => json_encode([
                    'paystack_split_reference' => $splitReference,
                    'paystack_split_type' => 'flat',
                    'paystack_split_share' => (int) $subaccount['share'],
                ]),
            ];
        }

        return [
            'split' => [
                'type' => 'flat',
                'bearer_type' => $bearerType,
                'reference' => $splitReference,
                'subaccounts' => $subaccounts->all(),
            ],
        ];
    }
}
