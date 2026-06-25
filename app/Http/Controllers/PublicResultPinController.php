<?php

namespace App\Http\Controllers;

use App\Models\ResultPinOrder;
use App\Models\ResultPinProduct;
use App\Services\PaystackService;
use App\Services\ResultPins\ResultPinPurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class PublicResultPinController extends Controller
{
    public function __construct(
        protected PaystackService $paystack,
        protected ResultPinPurchaseService $purchaseService,
    ) {}

    public function index(Request $request)
    {
        $this->purchaseService->syncProviderProductsSafely();

        $selectedProductId = ResultPinProduct::active()
            ->whereKey($request->integer('product'))
            ->value('id');

        return Inertia::render('Public/ResultPins/Index', [
            'products' => ResultPinProduct::active()->ordered()->get(),
            'selectedProductId' => $selectedProductId,
        ]);
    }

    public function purchase(Request $request)
    {
        $this->purchaseService->syncProviderProductsSafely();

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'product_id' => 'required|integer|exists:result_pin_products,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $product = ResultPinProduct::active()->findOrFail($validated['product_id']);
        $quantity = (int) $validated['quantity'];
        $totalAmount = $this->purchaseService->totalAmount($product, $quantity);

        $order = ResultPinOrder::create([
            'result_pin_product_id' => $product->id,
            'reference' => ResultPinOrder::generateReference(),
            'channel' => 'public',
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'buyer_email' => $validated['email'],
            'buyer_phone' => $validated['phone'],
            'provider_response' => [
                'payment_gateway' => 'paystack',
                'payment_status' => 'pending',
            ],
        ]);

        $payment = $this->paystack->initializeTransaction(
            email: $validated['email'],
            amountInKobo: (int) ($totalAmount * 100),
            reference: $order->reference,
            callbackUrl: route('public.result-pins.callback'),
        );

        if (!$payment['success']) {
            $order->markFailed($payment['message'] ?? 'Payment gateway initialization failed.');

            return back()->with('error', $payment['message']);
        }

        return inertia()->location($payment['authorization_url']);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        abort_unless($reference, 404);

        $order = ResultPinOrder::with('product')
            ->where('reference', $reference)
            ->where('channel', 'public')
            ->firstOrFail();

        $payment = $this->paystack->verifyTransaction($reference);
        if (!$payment['success'] || ($payment['status'] ?? null) !== 'success') {
            $order->markFailed($payment['message'] ?? 'Payment was not successful.');

            return redirect()->route('public.result-pins.index')
                ->with('error', 'Payment was not completed.');
        }

        if ((float) ($payment['amount'] ?? 0) < (float) $order->total_amount) {
            $order->markFailed('Paid amount is lower than the expected result PIN order amount.');

            return redirect()->route('public.result-pins.index')
                ->with('error', 'Payment amount could not be validated for this order.');
        }

        if ($order->status !== 'completed') {
            try {
                $order = $this->purchaseService->fulfillPaidGuestOrder($order);
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

                return redirect()->route('public.result-pins.show', ['order' => $order->reference])
                    ->with('error', 'Payment succeeded, but PIN purchase failed. Please contact support with your order reference.');
            }
        }

        return redirect()->route('public.result-pins.show', ['order' => $order->reference])
            ->with('success', 'Result PINs purchased successfully.');
    }

    public function show(ResultPinOrder $order)
    {
        abort_unless($order->channel === 'public', 404);

        return Inertia::render('Public/ResultPins/Show', [
            'order' => $order->load('product'),
        ]);
    }

    public function login()
    {
        return Inertia::render('Public/ResultPins/Login');
    }

    public function loginWithEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $orders = ResultPinOrder::with('product')
            ->where('channel', 'public')
            ->where('buyer_email', $validated['email'])
            ->latest()
            ->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'No public result PIN order was found for this email.');
        }

        return Inertia::render('Public/ResultPins/Orders', [
            'email' => $validated['email'],
            'orders' => $orders,
        ]);
    }
}
