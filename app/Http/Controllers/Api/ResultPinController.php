<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ResultPinProduct;
use App\Services\ResultPins\ResultPinPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ResultPinController extends Controller
{
    public function __construct(
        protected ResultPinPurchaseService $purchaseService,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $this->purchaseService->syncProviderProductsSafely();

        return response()->json([
            'success' => true,
            'data' => ResultPinProduct::active()
                ->ordered()
                ->get()
                ->map(fn (ResultPinProduct $product) => $this->formatProduct($product, $request->user())),
        ]);
    }

    public function purchase(Request $request): JsonResponse
    {
        $this->purchaseService->syncProviderProductsSafely();

        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:result_pin_products,id',
            'card_type_id' => 'nullable|string|max:50',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        if (empty($validated['product_id']) && empty($validated['card_type_id'])) {
            return response()->json([
                'success' => false,
                'error' => 'Either product_id or card_type_id is required.',
                'error_code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $product = ResultPinProduct::active()
            ->when(! empty($validated['product_id']), fn ($query) => $query->whereKey($validated['product_id']))
            ->when(empty($validated['product_id']), fn ($query) => $query->where('provider_card_type_id', $validated['card_type_id']))
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'error' => 'Result PIN product is not available.',
                'error_code' => 'PRODUCT_UNAVAILABLE',
            ], 404);
        }

        try {
            $branch = $request->input('branch');

            $order = $this->purchaseService->purchaseForUser(
                user: $request->user(),
                product: $product,
                quantity: (int) $validated['quantity'],
                channel: 'api',
                branch: $branch instanceof Branch ? $branch : null,
            );

            return response()->json([
                'success' => true,
                'data' => $this->formatOrder($order),
            ]);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();

            return response()->json([
                'success' => false,
                'error' => $message,
                'error_code' => str_contains(strtolower($message), 'insufficient') ? 'INSUFFICIENT_FUNDS' : 'PIN_PURCHASE_FAILED',
            ], str_contains(strtolower($message), 'insufficient') ? 402 : 400);
        }
    }

    private function formatProduct(ResultPinProduct $product, $user = null): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'board' => $product->board,
            'card_type_id' => $product->provider_card_type_id,
            'price' => $this->purchaseService->unitPriceFor($product, $user),
            'default_price' => (float) $product->price,
            'min_quantity' => $product->min_quantity,
            'max_quantity' => $product->max_quantity,
        ];
    }

    private function formatOrder($order): array
    {
        return [
            'reference' => $order->reference,
            'product' => $this->formatProduct($order->product, $order->user),
            'quantity' => $order->quantity,
            'unit_price' => (float) $order->unit_price,
            'total_amount' => (float) $order->total_amount,
            'status' => $order->status,
            'provider_reference' => $order->provider_reference,
            'pins' => $order->pins ?? [],
            'purchased_at' => $order->purchased_at?->toISOString(),
        ];
    }
}
