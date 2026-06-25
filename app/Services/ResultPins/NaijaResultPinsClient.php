<?php

namespace App\Services\ResultPins;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class NaijaResultPinsClient
{
    public function configured(): bool
    {
        return filled(config('services.naija_result_pins.token'));
    }

    public function account(): array
    {
        return $this->request()->get($this->url('account'))->throw()->json() ?? [];
    }

    public function products(): array
    {
        $response = $this->request()->get($this->url(''))->throw()->json() ?? [];

        return $this->extractProducts($response);
    }

    public function buy(string $cardTypeId, int $quantity): array
    {
        $response = $this->request()
            ->post($this->url('exam-card/buy'), [
                'card_type_id' => $cardTypeId,
                'quantity' => (string) $quantity,
            ])
            ->throw()
            ->json() ?? [];

        if (($response['status'] ?? false) !== true) {
            throw new ResultPinProviderException(
                (string) ($response['message'] ?? 'Result PIN provider rejected the purchase.'),
                isset($response['code']) ? (string) $response['code'] : null,
                $response,
            );
        }

        return [
            'status' => true,
            'code' => (string) ($response['code'] ?? '000'),
            'message' => (string) ($response['message'] ?? 'Result PIN purchase successful.'),
            'reference' => $response['reference'] ?? null,
            'quantity' => (int) ($response['quantity'] ?? $quantity),
            'amount' => isset($response['amount']) ? (float) $response['amount'] : null,
            'old_balance' => isset($response['old_balance']) ? (float) $response['old_balance'] : null,
            'new_balance' => isset($response['new_balance']) ? (float) $response['new_balance'] : null,
            'transaction_date' => $response['transaction_date'] ?? null,
            'cards' => $this->normalizeCards($response['cards'] ?? []),
            'raw' => $response,
        ];
    }

    private function request(): PendingRequest
    {
        $token = config('services.naija_result_pins.token');

        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.naija_result_pins.timeout', 45))
            ->when($token, fn (PendingRequest $request) => $request->withToken($token));
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) (config('services.naija_result_pins.base_url') ?: 'https://www.naijaresultpins.com/api/v1'), '/');
        $path = trim($path, '/');

        return $path === '' ? $baseUrl : "{$baseUrl}/{$path}";
    }

    private function normalizeCards(array $cards): array
    {
        return collect($cards)
            ->map(fn (array $card) => [
                'pin' => (string) ($card['pin'] ?? ''),
                'serial_no' => (string) ($card['serial_no'] ?? $card['serial'] ?? ''),
            ])
            ->filter(fn (array $card) => $card['pin'] !== '' || $card['serial_no'] !== '')
            ->values()
            ->all();
    }

    private function extractProducts(array $response): array
    {
        if ($this->looksLikeProductList($response)) {
            return $response;
        }

        foreach (['data', 'products', 'cards', 'exam_cards', 'examCards', 'card_types', 'cardTypes'] as $key) {
            if (! isset($response[$key]) || ! is_array($response[$key])) {
                continue;
            }

            if ($this->looksLikeProductList($response[$key])) {
                return $response[$key];
            }

            $nested = $this->extractProducts($response[$key]);
            if ($nested !== []) {
                return $nested;
            }
        }

        return [];
    }

    private function looksLikeProductList(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item)) {
                return false;
            }

            if ($this->productCardTypeId($item) !== null) {
                return true;
            }
        }

        return $value === [];
    }

    private function productCardTypeId(array $product): ?string
    {
        foreach (['card_type_id', 'cardTypeId', 'cardTypeID', 'product_id', 'productId', 'id'] as $key) {
            if (isset($product[$key]) && $product[$key] !== '') {
                return (string) $product[$key];
            }
        }

        return null;
    }
}
