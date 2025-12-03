<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DedicatedVirtualAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_number',
        'account_name',
        'bank_name',
        'bank_id',
        'bank_slug',
        'customer_id',
        'customer_code',
        'account_reference',
        'active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the user that owns this account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted account details.
     */
    public function getFormattedDetailsAttribute(): string
    {
        return "{$this->bank_name} - {$this->account_number}";
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Scope for active accounts only.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}