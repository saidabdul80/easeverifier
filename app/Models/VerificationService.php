<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerificationService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'default_price',
        'cost_price',
        'is_active',
        'sort_order',
        
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the service providers for this service.
     */
    public function providers(): HasMany
    {
        return $this->hasMany(ServiceProvider::class);
    }

    /**
     * Get active providers ordered by priority.
     */
    public function activeProviders()
    {
        return $this->providers()
            ->where('is_active', true)
            ->orderBy('priority');
    }

    /**
     * Get the primary (highest priority) active provider.
     */
    public function primaryProvider()
    {
        return $this->activeProviders()->first();
    }

    /**
     * Get all verification requests for this service.
     */
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    /**
     * Get customer pricing for this service.
     */
    public function customerPricing(): HasMany
    {
        return $this->hasMany(CustomerServicePricing::class);
    }

    /**
     * Get profit margin percentage.
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->cost_price <= 0) {
            return 100;
        }
        return (($this->default_price - $this->cost_price) / $this->cost_price) * 100;
    }

    /**
     * Scope for active services.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}

