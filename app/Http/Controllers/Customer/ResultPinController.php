<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ResultPinOrder;
use App\Models\ResultPinProduct;
use App\Services\ResultPins\ResultPinPurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class ResultPinController extends Controller
{
    public function __construct(
        protected ResultPinPurchaseService $purchaseService,
    ) {}

    public function index(Request $request)
    {
        $this->purchaseService->syncProviderProductsSafely();

        return Inertia::render('Customer/ResultPins/Index', [
            'products' => ResultPinProduct::active()
                ->ordered()
                ->get()
                ->map(fn (ResultPinProduct $product) => array_merge($product->toArray(), [
                    'price' => $this->purchaseService->unitPriceFor($product, $request->user()),
                    'default_price' => (float) $product->price,
                ])),
            'orders' => ResultPinOrder::with('product')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(15),
            'walletBalance' => $request->user()->wallet?->total_balance ?? 0,
        ]);
    }

    public function purchase(Request $request)
    {
        $this->purchaseService->syncProviderProductsSafely();

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:result_pin_products,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $product = ResultPinProduct::active()->findOrFail($validated['product_id']);

        try {
            $order = $this->purchaseService->purchaseForUser(
                user: $request->user(),
                product: $product,
                quantity: (int) $validated['quantity'],
                channel: 'customer',
            );

            return redirect()->route('customer.result-pins.show', $order)
                ->with('success', 'Result PINs purchased successfully.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function show(Request $request, ResultPinOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return Inertia::render('Customer/ResultPins/Show', [
            'order' => $order->load('product'),
        ]);
    }
}
