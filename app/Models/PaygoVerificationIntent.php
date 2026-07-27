<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaygoVerificationIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_paygo_service_id',
        'user_id',
        'verification_service_id',
        'flow_type',
        'transaction_id',
        'verification_request_id',
        'reference',
        'nin_hash',
        'lookup_hash',
        'lookup_label',
        'payload',
        'amount',
        'system_price_snapshot',
        'status',
        'verification_attempts',
        'max_fetches_snapshot',
        'reference_fetches',
        'buyer_phone',
        'paid_at',
        'used_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'system_price_snapshot' => 'decimal:2',
            'payload' => 'encrypted:array',
            'verification_attempts' => 'integer',
            'max_fetches_snapshot' => 'integer',
            'reference_fetches' => 'integer',
            'paid_at' => 'datetime',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public static function generateReference(): string
    {
        return 'PGO-'.Str::upper(Str::random(10)).'-'.time();
    }

    public static function hashNin(string $nin): string
    {
        return hash('sha256', preg_replace('/\D+/', '', $nin));
    }

    public static function hashLookup(string $value): string
    {
        return hash('sha256', trim(strtolower($value)));
    }

    public function isResultFlow(): bool
    {
        return $this->flow_type === 'result';
    }

    public function paygoService(): BelongsTo
    {
        return $this->belongsTo(CustomerPaygoService::class, 'customer_paygo_service_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verificationService(): BelongsTo
    {
        return $this->belongsTo(VerificationService::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function verificationRequest(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class);
    }
}
