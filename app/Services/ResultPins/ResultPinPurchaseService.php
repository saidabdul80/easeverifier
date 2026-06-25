<?php

namespace App\Services\ResultPins;

use App\Models\Branch;
use App\Models\ResultPinOrder;
use App\Models\ResultPinProduct;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ResultPinPurchaseService
{
    public function __construct(
        protected NaijaResultPinsClient $provider,
    ) {}

    public function purchaseForUser(
        User $user,
        ResultPinProduct $product,
        int $quantity,
        string $channel = 'api',
        ?Branch $branch = null,
        array $buyer = [],
    ): ResultPinOrder {
        $this->validateQuantity($product, $quantity);

        $wallet = $branch?->wallet ?? $user->wallet;
        if (!$wallet) {
            throw new \RuntimeException('Customer wallet was not found.');
        }

        $unitPrice = $this->unitPriceFor($product, $user);
        $totalAmount = $this->totalAmount($product, $quantity, $user);
        if (!$wallet->hasSufficientFunds($totalAmount)) {
            throw new \RuntimeException('Insufficient wallet balance.');
        }

        $transaction = $wallet->debit(
            $totalAmount,
            'verification',
            "{$product->name} PIN purchase ({$quantity})",
            [
                'service' => 'result_pin_purchase',
                'product_id' => $product->id,
                'quantity' => $quantity,
                'channel' => $channel,
            ],
        );

        $order = ResultPinOrder::create([
            'user_id' => $user->id,
            'branch_id' => $branch?->id,
            'result_pin_product_id' => $product->id,
            'transaction_id' => $transaction->id,
            'reference' => ResultPinOrder::generateReference(),
            'channel' => $channel,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'status' => 'processing',
            'buyer_name' => $buyer['name'] ?? $user->name,
            'buyer_email' => $buyer['email'] ?? $user->email,
            'buyer_phone' => $buyer['phone'] ?? $user->phone ?? null,
        ]);

        try {
            $providerResponse = $this->provider->buy($product->provider_card_type_id, $quantity);
            $cards = $providerResponse['cards'] ?? [];

            if (count($cards) < $quantity) {
                throw new ResultPinProviderException(
                    'Provider returned fewer cards than requested.',
                    'INCOMPLETE_PROVIDER_RESPONSE',
                    $providerResponse['raw'] ?? $providerResponse,
                );
            }

            $order->markCompleted($providerResponse, $cards);

            return $order->fresh(['product', 'transaction']);
        } catch (Throwable $exception) {
            $order->markFailed(
                $exception->getMessage(),
                $exception instanceof ResultPinProviderException ? $exception->providerResponse() : null,
            );

            $transaction->refund('Refund for failed result PIN purchase '.$order->reference);

            throw $exception;
        }
    }

    public function purchaseForAdmin(ResultPinProduct $product, int $quantity, User $admin): ResultPinOrder
    {
        $this->validateQuantity($product, $quantity);

        $order = ResultPinOrder::create([
            'user_id' => $admin->id,
            'result_pin_product_id' => $product->id,
            'reference' => ResultPinOrder::generateReference(),
            'channel' => 'admin',
            'quantity' => $quantity,
            'unit_price' => $this->unitPriceFor($product),
            'total_amount' => $this->totalAmount($product, $quantity),
            'status' => 'processing',
            'buyer_name' => $admin->name,
            'buyer_email' => $admin->email,
        ]);

        try {
            $providerResponse = $this->provider->buy($product->provider_card_type_id, $quantity);
            $order->markCompleted($providerResponse, $providerResponse['cards'] ?? []);

            return $order->fresh(['product']);
        } catch (Throwable $exception) {
            $order->markFailed(
                $exception->getMessage(),
                $exception instanceof ResultPinProviderException ? $exception->providerResponse() : null,
            );

            throw $exception;
        }
    }

    public function fulfillPendingOrderForUser(ResultPinOrder $order, User $user, ?Branch $branch = null): ResultPinOrder
    {
        $product = $order->product;
        if (!$product) {
            throw new \RuntimeException('Result PIN product was not found for this order.');
        }

        $this->validateQuantity($product, $order->quantity);

        if ($order->status === 'completed') {
            return $order->fresh(['product', 'transaction']);
        }

        $wallet = $branch?->wallet ?? $user->wallet;
        if (!$wallet) {
            throw new \RuntimeException('Customer wallet was not found.');
        }

        if (!$wallet->hasSufficientFunds((float) $order->total_amount)) {
            throw new \RuntimeException('Insufficient wallet balance.');
        }

        $transaction = $wallet->debit(
            (float) $order->total_amount,
            'verification',
            "{$product->name} PIN purchase ({$order->quantity})",
            [
                'service' => 'result_pin_purchase',
                'product_id' => $product->id,
                'quantity' => $order->quantity,
                'channel' => $order->channel,
                'order_reference' => $order->reference,
            ],
        );

        $order->update([
            'transaction_id' => $transaction->id,
            'status' => 'processing',
        ]);

        try {
            $providerResponse = $this->provider->buy($product->provider_card_type_id, $order->quantity);
            $cards = $providerResponse['cards'] ?? [];

            if (count($cards) < $order->quantity) {
                throw new ResultPinProviderException(
                    'Provider returned fewer cards than requested.',
                    'INCOMPLETE_PROVIDER_RESPONSE',
                    $providerResponse['raw'] ?? $providerResponse,
                );
            }

            $order->markCompleted($providerResponse, $cards);

            return $order->fresh(['product', 'transaction']);
        } catch (Throwable $exception) {
            $order->markFailed(
                $exception->getMessage(),
                $exception instanceof ResultPinProviderException ? $exception->providerResponse() : null,
            );

            $transaction->refund('Refund for failed result PIN purchase '.$order->reference);

            throw $exception;
        }
    }

    public function fulfillPaidGuestOrder(ResultPinOrder $order): ResultPinOrder
    {
        $product = $order->product;
        if (!$product) {
            throw new \RuntimeException('Result PIN product was not found for this order.');
        }

        $this->validateQuantity($product, $order->quantity);

        if ($order->status === 'completed') {
            return $order->fresh(['product']);
        }

        $order->update(['status' => 'processing']);

        try {
            $providerResponse = $this->provider->buy($product->provider_card_type_id, $order->quantity);
            $cards = $providerResponse['cards'] ?? [];

            if (count($cards) < $order->quantity) {
                throw new ResultPinProviderException(
                    'Provider returned fewer cards than requested.',
                    'INCOMPLETE_PROVIDER_RESPONSE',
                    $providerResponse['raw'] ?? $providerResponse,
                );
            }

            $order->markCompleted($providerResponse, $cards);

            return $order->fresh(['product']);
        } catch (Throwable $exception) {
            $order->markFailed(
                $exception->getMessage(),
                $exception instanceof ResultPinProviderException ? $exception->providerResponse() : null,
            );

            throw $exception;
        }
    }

    public function syncProviderProducts(): int
    {
        if (! $this->provider->configured()) {
            return 0;
        }

        $count = 0;

        foreach ($this->provider->products() as $remoteProduct) {
            $cardTypeId = $this->remoteValue($remoteProduct, [
                'card_type_id',
                'cardTypeId',
                'cardTypeID',
                'product_id',
                'productId',
                'id',
            ]);

            if ($cardTypeId === null) {
                continue;
            }

            $name = (string) ($this->remoteValue($remoteProduct, [
                'name',
                'title',
                'product_name',
                'productName',
                'card_name',
                'cardName',
                'card_type',
                'cardType',
                'type',
            ]) ?? "Result PIN {$cardTypeId}");

            $price = (float) ($this->remoteValue($remoteProduct, [
                'price',
                'amount',
                'unit_amount',
                'unitAmount',
                'selling_price',
                'sellingPrice',
                'sale_price',
                'salePrice',
                'unit_price',
                'unitPrice',
            ]) ?? 0);

            $costPrice = (float) ($this->remoteValue($remoteProduct, [
                'cost_price',
                'costPrice',
                'dealer_price',
                'dealerPrice',
                'wholesale_price',
                'wholesalePrice',
                'unit_amount',
                'unitAmount',
            ]) ?? $price);
            $availability = strtolower((string) ($this->remoteValue($remoteProduct, ['availability', 'status']) ?? 'in stock'));

            $product = ResultPinProduct::firstOrNew([
                'provider' => 'naijaresultpins',
                'provider_card_type_id' => (string) $cardTypeId,
            ]);

            $product->fill(
                [
                    'name' => $name,
                    'slug' => $product->exists ? $product->slug : Str::slug($name.'-'.$cardTypeId),
                    'board' => $this->inferBoard($name),
                    'description' => $this->remoteValue($remoteProduct, ['description', 'details']),
                    'price' => (float) $product->price > 0 ? $product->price : ($price > 0 ? $price : 0),
                    'cost_price' => $costPrice > 0 ? $costPrice : 0,
                    'min_quantity' => (int) ($this->remoteValue($remoteProduct, ['min_quantity', 'minQuantity']) ?? ($product->min_quantity ?: 1)),
                    'max_quantity' => (int) ($this->remoteValue($remoteProduct, ['max_quantity', 'maxQuantity']) ?? ($product->max_quantity ?: 100)),
                    'is_active' => ! str_contains($availability, 'out') && ! str_contains($availability, 'unavailable'),
                    'metadata' => $remoteProduct,
                ],
            );
            $product->save();

            $count++;
        }

        return $count;
    }

    public function syncProviderProductsSafely(): int
    {
        try {
            return $this->syncProviderProducts();
        } catch (Throwable $exception) {
            Log::warning('Result PIN provider product sync failed.', [
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    public function completeWalletFunding(Transaction $transaction, array $paymentResult): void
    {
        if ($transaction->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($transaction, $paymentResult) {
            $wallet = $transaction->wallet()->lockForUpdate()->firstOrFail();
            $balanceBefore = $wallet->balance;
            $bonusBalanceBefore = $wallet->bonus_balance;

            $wallet->balance += (float) $transaction->amount;
            $wallet->save();

            $transaction->update([
                'status' => 'completed',
                'balance_before' => $balanceBefore,
                'bonus_balance_before' => $bonusBalanceBefore,
                'balance_after' => $wallet->balance,
                'bonus_balance_after' => $wallet->bonus_balance,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'paid_at' => $paymentResult['paid_at'] ?? now(),
                    'channel' => $paymentResult['channel'] ?? null,
                    'payment_reference' => $paymentResult['reference'] ?? null,
                ]),
            ]);
        });
    }

    public function totalAmount(ResultPinProduct $product, int $quantity, ?User $user = null): float
    {
        return round($this->unitPriceFor($product, $user) * $quantity, 2);
    }

    public function unitPriceFor(ResultPinProduct $product, ?User $user = null): float
    {
        if ($user) {
            $customPrice = $user->resultPinPricing()
                ->where('result_pin_product_id', $product->id)
                ->where('is_active', true)
                ->first();

            if ($customPrice) {
                return (float) $customPrice->price;
            }
        }

        return (float) $product->price;
    }

    private function validateQuantity(ResultPinProduct $product, int $quantity): void
    {
        if (!$product->is_active) {
            throw new \RuntimeException('Result PIN product is not available.');
        }

        if ($quantity < $product->min_quantity || $quantity > $product->max_quantity) {
            throw new \RuntimeException("Quantity must be between {$product->min_quantity} and {$product->max_quantity}.");
        }
    }

    private function inferBoard(string $name): ?string
    {
        $upper = strtoupper($name);

        foreach (['WAEC', 'NECO', 'NABTEB', 'NBAIS'] as $board) {
            if (str_contains($upper, $board)) {
                return strtolower($board);
            }
        }

        return null;
    }

    private function remoteValue(array $product, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $product) && $product[$key] !== null && $product[$key] !== '') {
                return $product[$key];
            }
        }

        return null;
    }
}
