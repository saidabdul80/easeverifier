<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaygoPaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'paygo_verification_intent_id', 'attempt_number', 'reference', 'status',
        'paystack_gateway_account_id', 'paystack_gateway_owner_type', 'paystack_environment',
        'paystack_key_fingerprint', 'settlement_strategy', 'amount', 'system_price_snapshot',
        'authorization_url', 'access_code', 'initialized_at', 'last_verified_at', 'paid_at',
        'duplicate_payment_at', 'last_error', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'amount' => 'decimal:2',
            'system_price_snapshot' => 'decimal:2',
            'initialized_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'paid_at' => 'datetime',
            'duplicate_payment_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaygoVerificationIntent::class, 'paygo_verification_intent_id');
    }

    public function paystackGatewayAccount(): BelongsTo
    {
        return $this->belongsTo(PaystackGatewayAccount::class);
    }
}
