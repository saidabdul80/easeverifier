<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
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
    public function initializeTransaction(
        string $email,
        int $amountInKobo,
        string $reference,
        ?string $callbackUrl = null,
        array $options = [],
    ): array
    {
        $payload = array_merge([
            'email' => $email,
            'amount' => $amountInKobo,
            'reference' => $reference,
            'callback_url' => $callbackUrl ?? route('customer.payment.callback'),
            'currency' => 'NGN',
        ], $options);

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        if ($response->successful() && $response->json('status')) {
            return [
                'success' => true,
                'authorization_url' => $response->json('data.authorization_url'),
                'access_code' => $response->json('data.access_code'),
                'reference' => $response->json('data.reference'),
            ];
        }

        Log::error('Paystack initialize failed', [
            'response' => $response->json(),
            'payload' => $this->safeInitializePayloadForLog($payload),
        ]);
        
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

    private function safeInitializePayloadForLog(array $payload): array
    {
        unset($payload['email']);

        return $payload;
    }

    /**
     * List supported banks from Paystack.
     */
    public function listBanks(string $country = 'nigeria'): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/bank", [
                'country' => $country,
                'perPage' => 100,
            ]);

        if ($response->successful() && $response->json('status')) {
            return [
                'success' => true,
                'banks' => collect($response->json('data', []))
                    ->map(fn (array $bank) => [
                        'name' => $bank['name'] ?? null,
                        'code' => $bank['code'] ?? null,
                        'slug' => $bank['slug'] ?? null,
                        'type' => $bank['type'] ?? null,
                        'currency' => $bank['currency'] ?? null,
                    ])
                    ->filter(fn (array $bank) => filled($bank['name']) && filled($bank['code']))
                    ->values()
                    ->all(),
            ];
        }

        Log::error('Paystack bank list failed', ['response' => $response->json()]);

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Failed to fetch Paystack banks',
        ];
    }

    /**
     * Resolve a bank account name through Paystack.
     */
    public function resolveBankAccount(string $accountNumber, string $bankCode): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/bank/resolve", [
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                ]);
        } catch (ConnectionException $exception) {
            Log::error('Paystack bank account resolve connection failed', ['message' => $exception->getMessage()]);

            return [
                'success' => false,
                'message' => 'Unable to reach Paystack to resolve this bank account.',
            ];
        }

        if ($response->successful() && $response->json('status')) {
            return [
                'success' => true,
                'account_number' => $response->json('data.account_number'),
                'account_name' => $response->json('data.account_name'),
                'bank_id' => $response->json('data.bank_id'),
                'data' => $response->json('data'),
            ];
        }

        Log::error('Paystack bank account resolve failed', ['response' => $response->json()]);

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Failed to resolve bank account',
        ];
    }

    /**
     * Create a Paystack subaccount from settlement bank details.
     */
    public function createSubaccount(
        string $businessName,
        string $bankCode,
        string $accountNumber,
        ?string $description = null,
        array $metadata = [],
    ): array
    {
        $payload = [
            'business_name' => $businessName,
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            'percentage_charge' => 0,
        ];

        if (filled($description)) {
            $payload['description'] = $description;
        }

        if ($metadata !== []) {
            $payload['metadata'] = json_encode($metadata);
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/subaccount", $payload);
        } catch (ConnectionException $exception) {
            Log::error('Paystack subaccount creation connection failed', ['message' => $exception->getMessage()]);

            return [
                'success' => false,
                'message' => 'Unable to reach Paystack to create this subaccount.',
            ];
        }

        if ($response->successful() && $response->json('status')) {
            $data = $response->json('data', []);

            return [
                'success' => true,
                'subaccount_code' => $data['subaccount_code'] ?? null,
                'account_name' => $data['account_name'] ?? null,
                'account_number' => $data['account_number'] ?? $accountNumber,
                'bank_name' => $data['settlement_bank'] ?? null,
                'data' => $data,
            ];
        }

        Log::error('Paystack subaccount creation failed', ['response' => $response->json()]);

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Failed to create Paystack subaccount',
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

    /**
     * Create a dedicated virtual account for a customer.
     */
    public function createDedicatedVirtualAccount(
        string $email,
        string $firstName,
        string $lastName,
        ?string $phone = null,
        string $preferredBank = 'wema-bank'
    ): array {
        $payload = [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'preferred_bank' => $preferredBank, // wema-bank, titan-paystack
        ];

        if ($phone) {
            $payload['phone'] = $phone;
        }

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/dedicated_account", $payload);

        if ($response->successful() && $response->json('status')) {
            $data = $response->json('data');
            
            return [
                'success' => true,
                'account_number' => $data['account_number'],
                'account_name' => $data['account_name'],
                'bank_name' => $data['bank']['name'],
                'bank_id' => $data['bank']['id'],
                'bank_slug' => $data['bank']['slug'],
                'customer_id' => $data['customer']['id'],
                'customer_code' => $data['customer']['customer_code'],
                'account_reference' => $data['id'] ?? null,
                'active' => $data['active'] ?? true,
            ];
        }

        Log::error('Paystack DVA creation failed', ['response' => $response->json()]);
        
        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Failed to create dedicated account',
        ];
    }

    /**
     * Fetch all dedicated accounts for a customer.
     */
    public function listDedicatedAccounts(string $customerIdOrCode): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/dedicated_account", [
                'customer' => $customerIdOrCode,
            ]);

        if ($response->successful() && $response->json('status')) {
            return [
                'success' => true,
                'accounts' => $response->json('data'),
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Failed to fetch dedicated accounts',
        ];
    }

    /**
     * Deactivate a dedicated virtual account.
     */
    public function deactivateDedicatedAccount(string $accountReference): array
    {
        $response = Http::withToken($this->secretKey)
            ->delete("{$this->baseUrl}/dedicated_account/{$accountReference}");

        if ($response->successful() && $response->json('status')) {
            return [
                'success' => true,
                'message' => 'Account deactivated successfully',
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Failed to deactivate account',
        ];
    }

}
