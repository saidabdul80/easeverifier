<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaystackSplitLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'paygo_verification_intent_id',
        'payment_reference',
        'split_reference',
        'subaccount_code',
        'subaccount_label',
        'flat_amount',
        'flat_amount_kobo',
        'transaction_amount',
        'main_account_remainder',
        'status',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'flat_amount' => 'decimal:2',
            'flat_amount_kobo' => 'integer',
            'transaction_amount' => 'decimal:2',
            'main_account_remainder' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paygoIntent(): BelongsTo
    {
        return $this->belongsTo(PaygoVerificationIntent::class, 'paygo_verification_intent_id');
    }
}
