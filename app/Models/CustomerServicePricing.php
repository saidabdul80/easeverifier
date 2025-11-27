<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerServicePricing extends Model
{
    use HasFactory;

    protected $table = 'customer_service_pricing';

    protected $fillable = [
        'user_id',
        'verification_service_id',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the customer (user) for this pricing.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the verification service for this pricing.
     */
    public function verificationService(): BelongsTo
    {
        return $this->belongsTo(VerificationService::class);
    }
}

