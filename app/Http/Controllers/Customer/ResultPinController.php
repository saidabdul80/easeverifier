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

        $customer = $request->user()->customer;
        if ($customer && blank($customer->referral_code)) {
            $customer->forceFill(['referral_code' => \App\Models\Customer::generateReferralCode()])->save();
        }

        $this->purchaseService->creditPendingReferralBonusesForUser($request->user());
        $request->user()->load('wallet');

        return Inertia::render('Customer/ResultPins/Index', [
            'products' => ResultPinProduct::active()
                ->ordered()
                ->get()
                ->map(fn (ResultPinProduct $product) => array_merge($product->toArray(), [
                    'price' => $this->purchaseService->unitPriceFor($product, $request->user()),
                    'default_price' => (float) $product->price,
                ])),
            'orders' => ResultPinOrder::with('product')
                ->where(function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id)
                        ->orWhere('referred_by_user_id', $request->user()->id);
                })
                ->latest()
                ->paginate(15),
            'walletBalance' => $request->user()->wallet?->total_balance ?? 0,
            'referral' => [
                'code' => $customer?->referral_code,
                'link' => $customer?->referralLink(),
                'bonus_amount' => ResultPinPurchaseService::REFERRAL_BONUS_AMOUNT,
                'completed_orders' => ResultPinOrder::where('referred_by_user_id', $request->user()->id)
                    ->where('status', 'completed')
                    ->count(),
                'total_earned' => (float) ResultPinOrder::where('referred_by_user_id', $request->user()->id)
                    ->where('status', 'completed')
                    ->sum('referral_bonus_amount'),
            ],
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

            return redirect()->route('customer.result-pins.show', $order->reference)
                ->with('success', 'Result PINs purchased successfully.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function show(Request $request, string $order)
    {
        $order = ResultPinOrder::with('product')
            ->where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('referred_by_user_id', $request->user()->id);
            })
            ->where(function ($query) use ($order) {
                $query->where('reference', $order);

                if (ctype_digit($order)) {
                    $query->orWhere('id', (int) $order);
                }
            })
            ->firstOrFail();

        return Inertia::render('Customer/ResultPins/Show', [
            'order' => $order,
        ]);
    }
}
