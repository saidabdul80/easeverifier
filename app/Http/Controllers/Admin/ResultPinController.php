<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResultPinOrder;
use App\Models\ResultPinProduct;
use App\Services\ResultPins\NaijaResultPinsClient;
use App\Services\ResultPins\ResultPinPurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class ResultPinController extends Controller
{
    public function __construct(
        protected ResultPinPurchaseService $purchaseService,
        protected NaijaResultPinsClient $provider,
    ) {}

    public function index()
    {
        $this->purchaseService->syncProviderProductsSafely();

        return Inertia::render('Admin/ResultPins/Index', [
            'products' => ResultPinProduct::ordered()->get(),
            'orders' => ResultPinOrder::with(['product', 'user:id,name,email'])
                ->latest()
                ->paginate(15),
            'providerAccount' => $this->safeProviderAccount(),
        ]);
    }

    public function sync()
    {
        try {
            $count = $this->purchaseService->syncProviderProducts();

            return back()->with('success', "Synced {$count} result PIN products from provider.");
        } catch (Throwable $exception) {
            return back()->with('error', 'Provider sync failed: '.$exception->getMessage());
        }
    }

    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:result_pin_products,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $product = ResultPinProduct::findOrFail($validated['product_id']);

        try {
            $this->purchaseService->purchaseForAdmin($product, (int) $validated['quantity'], $request->user());

            return back()->with('success', 'Result PINs purchased successfully.');
        } catch (Throwable $exception) {
            return back()->with('error', 'Result PIN purchase failed: '.$exception->getMessage());
        }
    }

    public function updateProductPrice(Request $request, ResultPinProduct $product)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $product->update([
            'price' => $validated['price'],
            'is_active' => $validated['is_active'] ?? $product->is_active,
        ]);

        return back()->with('success', 'Result PIN product price updated successfully.');
    }

    private function safeProviderAccount(): ?array
    {
        try {
            return $this->provider->account();
        } catch (Throwable) {
            return null;
        }
    }
}
