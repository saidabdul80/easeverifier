<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerResultPinPricing extends Model
{
    use HasFactory;

    protected $table = 'customer_result_pin_pricing';

    protected $fillable = [
        'user_id',
        'result_pin_product_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ResultPinProduct::class, 'result_pin_product_id');
    }
}
