<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultPinProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'board',
        'provider',
        'provider_card_type_id',
        'description',
        'price',
        'cost_price',
        'min_quantity',
        'max_quantity',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ResultPinOrder::class);
    }

    public function customerPricing(): HasMany
    {
        return $this->hasMany(CustomerResultPinPricing::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
