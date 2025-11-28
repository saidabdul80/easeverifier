<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
        $this->baseUrl = config('services.paystack.base_url');
    }

    /**
     * Initialize a payment transaction.
     */
    public function initializeTransaction(string $email, int $amountInKobo, string $reference, ?string $callbackUrl = null): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => $amountInKobo,
                'reference' => $reference,
                'callback_url' => $callbackUrl ?? route('customer.payment.callback'),
                'currency' => 'NGN',
            ]);

        if ($response->successful() && $response->json('status')) {
            return [
                'success' => true,
                'authorization_url' => $response->json('data.authorization_url'),
                'access_code' => $response->json('data.access_code'),
                'reference' => $response->json('data.reference'),
            ];
        }

        Log::error('Paystack initialize failed', ['response' => $response->json()]);
        
        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Payment initialization failed',
        ];
    }

    /**
     * Verify a transaction by reference.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('status')) {
            $data = $response->json('data');
            
            return [
                'success' => true,
                'status' => $data['status'], // success, failed, abandoned
                'amount' => $data['amount'] / 100, // Convert from kobo to naira
                'reference' => $data['reference'],
                'paid_at' => $data['paid_at'] ?? null,
                'channel' => $data['channel'] ?? null,
                'customer_email' => $data['customer']['email'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Verification failed',
        ];
    }

    /**
     * Generate a unique payment reference.
     */
    public static function generateReference(): string
    {
        return 'PAY_' . strtoupper(bin2hex(random_bytes(10)));
    }

    /**
     * Get the public key for frontend use.
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}

