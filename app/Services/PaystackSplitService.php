<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPaystackSplitAccount;
use App\Models\PaystackGatewayAccount;
use RuntimeException;

class PaystackSplitService
{
    public function buildDynamicFlatSplit(
        ?Customer $customer,
        int $amountInKobo,
        string $paymentReference,
        ?PaystackGatewayAccount $gateway = null,
        ?int $systemAmountInKobo = null,
    ): ?array
    {
        if (! $customer || $amountInKobo <= 0) {
            return null;
        }

        if ($gateway) {
            return $this->buildCustomerGatewaySplit($customer, $gateway, $amountInKobo, $paymentReference, $systemAmountInKobo);
        }

        $accounts = $customer->paystackSplitAccounts()
            ->whereNull('paystack_gateway_account_id')
            ->where('beneficiary_type', 'customer')
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
                'gateway_owner_type' => 'system',
                'settlement_strategy' => 'system_gateway_customer_subaccount',
                'subaccounts' => $accounts
                    ->map(fn (CustomerPaystackSplitAccount $account) => [
                        'id' => $account->id,
                        'label' => $account->label,
                        'subaccount_code' => $account->subaccount_code,
                        'share' => $this->amountToKobo((float) $account->flat_amount),
                        'flat_amount' => (float) $account->flat_amount,
                        'beneficiary_type' => 'customer',
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function buildCustomerGatewaySplit(
        Customer $customer,
        PaystackGatewayAccount $gateway,
        int $amountInKobo,
        string $paymentReference,
        ?int $systemAmountInKobo,
    ): array {
        $account = $customer->paystackSplitAccounts()
            ->where('paystack_gateway_account_id', $gateway->id)
            ->where('beneficiary_type', 'system')
            ->where('is_active', true)
            ->first();

        if (! $account || blank($account->subaccount_code)) {
            throw new RuntimeException('This customer Paystack account is missing an active EaseVerifier settlement subaccount.');
        }

        $systemShare = (int) ($systemAmountInKobo ?? 0);

        if ($systemShare < 100 || $systemShare >= $amountInKobo) {
            throw new RuntimeException('The EaseVerifier settlement amount must be at least NGN 1.00 and lower than the transaction amount.');
        }

        $splitReference = 'SPLIT-'.$paymentReference;
        $customerRemainder = $amountInKobo - $systemShare;

        return [
            'payment_options' => [
                'subaccount' => $account->subaccount_code,
                'transaction_charge' => $customerRemainder,
                'bearer' => 'account',
                'metadata' => json_encode([
                    'paystack_split_reference' => $splitReference,
                    'paystack_split_type' => 'flat',
                    'paystack_split_share' => $systemShare,
                    'paystack_gateway_account_id' => $gateway->id,
                ]),
            ],
            'metadata' => [
                'applied' => true,
                'type' => 'flat',
                'bearer_type' => 'account',
                'reference' => $splitReference,
                'method' => 'single_subaccount',
                'total_split_amount' => $systemShare / 100,
                'total_split_amount_kobo' => $systemShare,
                'main_account_remainder' => $customerRemainder / 100,
                'main_account_remainder_kobo' => $customerRemainder,
                'wallet_credit_skipped' => true,
                'gateway_owner_type' => 'customer',
                'gateway_account_id' => $gateway->id,
                'settlement_strategy' => 'customer_gateway_system_subaccount',
                'subaccounts' => [[
                    'id' => $account->id,
                    'label' => $account->label,
                    'subaccount_code' => $account->subaccount_code,
                    'share' => $systemShare,
                    'flat_amount' => $systemShare / 100,
                    'beneficiary_type' => 'system',
                ]],
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
