<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\DedicatedVirtualAccount;
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
    // public function webhook(Request $request)
    // {
    //     // Verify webhook signature
    //     $signature = $request->header('x-paystack-signature');
    //     $payload = $request->getContent();
    //     $expectedSignature = hash_hmac('sha512', $payload, config('services.paystack.secret_key'));

    //     if ($signature !== $expectedSignature) {
    //         Log::warning('Invalid Paystack webhook signature');
    //         return response()->json(['status' => 'error'], 400);
    //     }

    //     $event = $request->input('event');
    //     $data = $request->input('data');

    //     if ($event === 'charge.success') {
    //         $reference = $data['reference'];
    //         $transaction = Transaction::where('reference', $reference)
    //             ->where('status', 'pending')
    //             ->first();

    //         if ($transaction) {
    //             DB::transaction(function () use ($transaction, $data) {
    //                 $wallet = $transaction->wallet;
    //                 $wallet->lockForUpdate();

    //                 $balanceBefore = $wallet->balance;
    //                 $wallet->balance += $data['amount'] / 100;
    //                 $wallet->save();

    //                 $transaction->update([
    //                     'status' => 'completed',
    //                     'balance_before' => $balanceBefore,
    //                     'balance_after' => $wallet->balance,
    //                 ]);
    //             });
    //         }
    //     }

    //     return response()->json(['status' => 'success']);
    // }

    /**
     * Create a dedicated virtual account for the customer.
     */
    public function createDedicatedAccount(Request $request)
    {
        $request->validate([
            'preferred_bank' => 'nullable|in:wema-bank,titan-paystack',
        ]);

        $user = $request->user();

        // Check if user already has an active DVA
        $existingAccount = DedicatedVirtualAccount::where('user_id', $user->id)
            ->where('active', true)
            ->first();

        if ($existingAccount) {
            return back()->with('info', 'You already have an active dedicated account.');
        }

        // Split user's full name
        $nameParts = explode(' ', $user->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? $firstName;

        $result = $this->paystack->createDedicatedVirtualAccount(
            email: $user->email,
            firstName: $firstName,
            lastName: $lastName,
            phone: $user->phone ?? null,
            preferredBank: $request->preferred_bank ?? 'wema-bank'
        );

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        // Save the dedicated account details
        DedicatedVirtualAccount::create([
            'user_id' => $user->id,
            'account_number' => $result['account_number'],
            'account_name' => $result['account_name'],
            'bank_name' => $result['bank_name'],
            'bank_id' => $result['bank_id'],
            'bank_slug' => $result['bank_slug'],
            'customer_id' => $result['customer_id'],
            'customer_code' => $result['customer_code'],
            'account_reference' => $result['account_reference'],
            'active' => $result['active'],
        ]);

        return back()->with('success', 'Dedicated account created successfully!');
    }

    /**
     * Get customer's dedicated virtual accounts.
     */
    public function getDedicatedAccounts(Request $request)
    {
        $user = $request->user();
        $accounts = DedicatedVirtualAccount::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Handle Paystack webhook for all events.
     */
    public function webhook(Request $request)
    {
        // Verify webhook signature
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha512', $payload, config('services.paystack.secret_key'));

        if ($signature !== $expectedSignature) {
            Log::warning('Invalid Paystack webhook signature', [
                'ip' => $request->ip(),
                'received_signature' => $signature,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info('Paystack webhook received', [
            'event' => $event,
            'reference' => $data['reference'] ?? null,
        ]);

        try {
            // Handle different webhook events
            switch ($event) {
                case 'charge.success':
                    $this->handleChargeSuccess($data);
                    break;

                case 'dedicatedaccount.assign.success':
                    $this->handleDedicatedAccountAssigned($data);
                    break;

                case 'dedicatedaccount.assign.failed':
                    Log::error('Dedicated account assignment failed', ['data' => $data]);
                    break;

                case 'transfer.success':
                case 'transfer.failed':
                    Log::info("Transfer event: {$event}", ['data' => $data]);
                    break;

                default:
                    Log::info('Unhandled webhook event', ['event' => $event]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle successful charge (from card payment or bank transfer).
     */
    protected function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'];
        $amount = $data['amount'] / 100; // Convert from kobo
        $channel = $data['channel'] ?? 'unknown';
        $customerEmail = $data['customer']['email'] ?? null;

        // Check if this is a DVA transfer
        if ($channel === 'dedicated_nuban') {
            $this->handleDedicatedAccountTransfer($data);
            return;
        }

        // Handle regular transaction
        $transaction = Transaction::where('reference', $reference)
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            Log::warning('Transaction not found for webhook', ['reference' => $reference]);
            return;
        }

        DB::transaction(function () use ($transaction, $data, $amount) {
            $wallet = $transaction->wallet;
            $wallet->lockForUpdate();

            $balanceBefore = $wallet->balance;
            $wallet->balance += $amount;
            $wallet->save();

            $transaction->update([
                'status' => 'completed',
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'paid_at' => $data['paid_at'] ?? now(),
                    'channel' => $data['channel'] ?? null,
                    'ip_address' => $data['ip_address'] ?? null,
                ]),
            ]);
        });

        Log::info('Charge success processed', ['reference' => $reference, 'amount' => $amount]);
    }

    /**
     * Handle dedicated virtual account transfer.
     */
    protected function handleDedicatedAccountTransfer(array $data): void
    {
        Log::info('DVA-transfer',[$data]);
        $customerCode = $data['receiver_account_number'];
        $amount = $data['amount'] / 100;
        $reference = $data['session_id'];

        if (!$customerCode) {
            Log::error('Customer code not found in DVA webhook', ['data' => $data]);
            return;
        }

        // Find the dedicated account
        $dedicatedAccount = DedicatedVirtualAccount::where('account_number', $customerCode)
            ->where('active', true)
            ->first();

        if (!$dedicatedAccount) {
            Log::error('Dedicated account not found', ['account_number' => $customerCode]);
            return;
        }

        $user = $dedicatedAccount->user;
        $wallet = $user->wallet;

        if (!$wallet) {
            Log::error('Wallet not found for user', ['user_id' => $user->id]);
            return;
        }

        // Create and complete transaction
        DB::transaction(function () use ($user, $wallet, $reference, $amount, $data, $dedicatedAccount) {
            $wallet->lockForUpdate();

            $balanceBefore = $wallet->balance;
            $wallet->balance += $amount;
            $wallet->save();

            Transaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'type' => 'credit',
                'category' => 'funding',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => "Bank transfer to {$dedicatedAccount->bank_name} account",
                'status' => 'completed',
                'metadata' => [
                    'payment_gateway' => 'paystack',
                    'channel' => 'dedicated_nuban',
                    'paid_at' => $data['paid_at'] ?? now(),
                    'account_number' => $dedicatedAccount->account_number,
                    'bank_name' => $dedicatedAccount->bank_name,
                    'sender_name' => $data['metadata']['sender_name'] ?? null,
                    'sender_account' => $data['metadata']['sender_account_number'] ?? null,
                ],
            ]);
        });

        Log::info('DVA transfer processed successfully', [
            'user_id' => $user->id,
            'amount' => $amount,
            'reference' => $reference,
        ]);
    }

    /**
     * Handle dedicated account assignment success.
     */
    protected function handleDedicatedAccountAssigned(array $data): void
    {
        $customerCode = $data['customer']['customer_code'] ?? null;

        if (!$customerCode) {
            Log::error('Customer code not found in assignment webhook');
            return;
        }

        Log::info('Dedicated account assigned', [
            'customer_code' => $customerCode,
            'account_number' => $data['dedicated_account']['account_number'] ?? null,
        ]);
    }
}

