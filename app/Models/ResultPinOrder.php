<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResultPinOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'result_pin_product_id',
        'transaction_id',
        'reference',
        'channel',
        'quantity',
        'unit_price',
        'total_amount',
        'provider_amount',
        'provider',
        'provider_reference',
        'status',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'pins',
        'provider_response',
        'error_message',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'provider_amount' => 'decimal:2',
            'pins' => 'array',
            'provider_response' => 'array',
            'purchased_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        return 'PIN-' . strtoupper(Str::random(10)) . '-' . time();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ResultPinProduct::class, 'result_pin_product_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function markCompleted(array $providerResponse, array $pins): void
    {
        $this->update([
            'status' => 'completed',
            'provider_reference' => $providerResponse['reference'] ?? null,
            'provider_amount' => isset($providerResponse['amount']) ? (float) $providerResponse['amount'] : null,
            'provider_response' => $providerResponse,
            'pins' => $pins,
            'error_message' => null,
            'purchased_at' => now(),
        ]);
    }

    public function markFailed(string $message, ?array $providerResponse = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
            'provider_response' => $providerResponse,
        ]);
    }
}
