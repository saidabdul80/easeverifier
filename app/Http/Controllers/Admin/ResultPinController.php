<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResultPinOrder;
use App\Models\ResultPinProduct;
use App\Services\PaystackService;
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
        protected PaystackService $paystack,
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
        $this->purchaseService->syncProviderProductsSafely();

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:result_pin_products,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $product = ResultPinProduct::findOrFail($validated['product_id']);
        $quantity = (int) $validated['quantity'];
        $totalAmount = $this->purchaseService->totalAmount($product, $quantity);

        try {
            $order = ResultPinOrder::create([
                'user_id' => $request->user()->id,
                'result_pin_product_id' => $product->id,
                'reference' => ResultPinOrder::generateReference(),
                'channel' => 'admin',
                'quantity' => $quantity,
                'unit_price' => $this->purchaseService->unitPriceFor($product),
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'buyer_name' => $request->user()->name,
                'buyer_email' => $request->user()->email,
                'provider_response' => [
                    'payment_gateway' => 'paystack',
                    'payment_status' => 'pending',
                ],
            ]);

            $payment = $this->paystack->initializeTransaction(
                email: $request->user()->email,
                amountInKobo: (int) ($totalAmount * 100),
                reference: $order->reference,
                callbackUrl: route('admin.result-pins.callback'),
            );

            if (! $payment['success']) {
                $order->markFailed($payment['message'] ?? 'Payment gateway initialization failed.');

                return back()->with('error', $payment['message'] ?? 'Payment gateway initialization failed.');
            }

            return inertia()->location($payment['authorization_url']);
        } catch (Throwable $exception) {
            return back()->with('error', 'Result PIN purchase failed: '.$exception->getMessage());
        }
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        abort_unless($reference, 404);

        $order = ResultPinOrder::with('product')
            ->where('reference', $reference)
            ->where('channel', 'admin')
            ->firstOrFail();

        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);

        $payment = $this->paystack->verifyTransaction($reference);
        if (! $payment['success'] || ($payment['status'] ?? null) !== 'success') {
            $order->markFailed($payment['message'] ?? 'Payment was not successful.');

            return redirect()->route('admin.result-pins.index')
                ->with('error', 'Payment was not completed.');
        }

        if ((float) ($payment['amount'] ?? 0) < (float) $order->total_amount) {
            $order->markFailed('Paid amount is lower than the expected result PIN order amount.');

            return redirect()->route('admin.result-pins.index')
                ->with('error', 'Payment amount could not be validated for this order.');
        }

        if ($order->status !== 'completed') {
            try {
                $order = $this->purchaseService->fulfillPaidProviderOrder($order);
                $order->update([
                    'provider_response' => array_merge($order->provider_response ?? [], [
                        'payment_gateway' => 'paystack',
                        'payment_status' => 'success',
                        'payment_reference' => $payment['reference'] ?? $reference,
                        'paid_at' => $payment['paid_at'] ?? now(),
                        'payment_channel' => $payment['channel'] ?? null,
                    ]),
                ]);
            } catch (Throwable $exception) {
                $order->markFailed($exception->getMessage());

                return redirect()->route('admin.result-pins.index')
                    ->with('error', 'Payment succeeded, but provider PIN purchase failed. Check the failed order before retrying.');
            }
        }

        return redirect()->route('admin.result-pins.index')
            ->with('success', 'Result PINs purchased successfully.');
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
