<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'business_type',
        'address',
        'city',
        'state',
        'country',
        'api_key',
        'api_secret',
        'webhook_url',
        'api_enabled',
        'rate_limit',
        'allowed_ips',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'api_enabled' => 'boolean',
            'rate_limit' => 'integer',
            'allowed_ips' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (Customer $customer) {
            // Create wallet for new customer
            $customer->user->wallet()->create([
                'balance' => 0,
                'bonus_balance' => 0,
            ]);
        });
    }

    /**
     * Get the user that owns this customer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet through the user.
     */
    public function wallet(): HasOne
    {
        return $this->hasOneThrough(Wallet::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /**
     * Get all verification requests through the user.
     */
    public function verificationRequests(): HasMany
    {
        return $this->hasManyThrough(VerificationRequest::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /**
     * Get all transactions through the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasManyThrough(Transaction::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /**
     * Generate API credentials for the customer.
     */
    public function generateApiCredentials(): array
    {
        $this->api_key = 'ev_live_' . Str::random(32);
        $this->api_secret = hash('sha256', Str::random(64));
        $this->save();

        return [
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret,
        ];
    }

    /**
     * Regenerate API secret only.
     */
    public function regenerateApiSecret(): string
    {
        $this->api_secret = hash('sha256', Str::random(64));
        $this->save();

        return $this->api_secret;
    }

    /**
     * Check if API access is enabled and configured.
     */
    public function hasApiAccess(): bool
    {
        return $this->api_enabled && $this->api_key !== null;
    }

    /**
     * Check if an IP is allowed (whitelist check).
     */
    public function isIpAllowed(string $ip): bool
    {
        // If no IPs are whitelisted, allow all
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Get the full address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Scope for customers with API access.
     */
    public function scopeWithApiAccess($query)
    {
        return $query->where('api_enabled', true)->whereNotNull('api_key');
    }
}

