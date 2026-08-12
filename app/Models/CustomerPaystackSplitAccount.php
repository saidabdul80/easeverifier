<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaystackSplitAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'label',
        'subaccount_code',
        'account_name',
        'bank_name',
        'bank_code',
        'account_number',
        'account_number_last4',
        'flat_amount',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'flat_amount' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'account_number' => 'encrypted',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
