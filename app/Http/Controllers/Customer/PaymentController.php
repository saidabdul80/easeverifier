<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected PaystackService $paystack
    ) {}

    /**
     * Initialize Paystack payment.
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $user = $request->user();
        $amount = (float) $request->amount;
        $reference = PaystackService::generateReference();

        // Create a pending transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'reference' => $reference,
            'type' => 'credit',
            'category' => 'funding',
            'amount' => $amount,
            'balance_before' => $user->wallet->balance,
            'balance_after' => $user->wallet->balance,
            'description' => 'Wallet funding via Paystack',
            'status' => 'pending',
            'metadata' => ['payment_gateway' => 'paystack'],
        ]);

        // Initialize Paystack payment
        $result = $this->paystack->initializeTransaction(
            email: $user->email,
            amountInKobo: (int) ($amount * 100),
            reference: $reference
        );

        if (!$result['success']) {
            $transaction->update(['status' => 'failed']);
            return back()->withErrors(['amount' => $result['message']]);
        }

        // Redirect to Paystack checkout
        return inertia()->location($result['authorization_url']);
    }

    /**
     * Handle Paystack callback after payment.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return redirect()->route('customer.wallet.index')
                ->with('error', 'Invalid payment reference');
        }

        // Verify the transaction
        $result = $this->paystack->verifyTransaction($reference);

        if (!$result['success']) {
            return redirect()->route('customer.wallet.index')
                ->with('error', 'Payment verification failed');
        }

        // Find the pending transaction
        $transaction = Transaction::where('reference', $reference)->first();

        if (!$transaction) {
            return redirect()->route('customer.wallet.index')
                ->with('error', 'Transaction not found');
        }

        // Already processed
        if ($transaction->status === 'completed') {
            return redirect()->route('customer.wallet.index')
                ->with('success', 'Payment already processed');
        }

        if ($result['status'] === 'success') {
            // Credit the wallet
            DB::transaction(function () use ($transaction, $result) {
                $wallet = $transaction->wallet;
                $wallet->lockForUpdate();

                $balanceBefore = $wallet->balance;
                $wallet->balance += $result['amount'];
                $wallet->save();

                $transaction->update([
                    'status' => 'completed',
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->balance,
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'paid_at' => $result['paid_at'],
                        'channel' => $result['channel'],
                    ]),
                ]);
            });

            return redirect()->route('customer.wallet.index')
                ->with('success', 'Payment successful! Your wallet has been credited.');
        }

        // Payment failed or abandoned
        $transaction->update(['status' => 'failed']);
        
        return redirect()->route('customer.wallet.index')
            ->with('error', 'Payment was not completed');
    }

    /**
     * Handle Paystack webhook.
     */
    public function webhook(Request $request)
    {
        // Verify webhook signature
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha512', $payload, config('services.paystack.secret_key'));

        if ($signature !== $expectedSignature) {
            Log::warning('Invalid Paystack webhook signature');
            return response()->json(['status' => 'error'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.success') {
            $reference = $data['reference'];
            $transaction = Transaction::where('reference', $reference)
                ->where('status', 'pending')
                ->first();

            if ($transaction) {
                DB::transaction(function () use ($transaction, $data) {
                    $wallet = $transaction->wallet;
                    $wallet->lockForUpdate();

                    $balanceBefore = $wallet->balance;
                    $wallet->balance += $data['amount'] / 100;
                    $wallet->save();

                    $transaction->update([
                        'status' => 'completed',
                        'balance_before' => $balanceBefore,
                        'balance_after' => $wallet->balance,
                    ]);
                });
            }
        }

        return response()->json(['status' => 'success']);
    }
}

