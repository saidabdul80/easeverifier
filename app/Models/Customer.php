<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    public const ACCOUNT_TYPES = [
        'individual',
        'business',
    ];

    public const EXPECTED_MONTHLY_VOLUMES = [
        '1-100',
        '101-500',
        '501-1000',
        '1001-5000',
        '5001+',
    ];

    protected $fillable = [
        'user_id',
        'company_name',
        'account_type',
        'business_type',
        'registration_number',
        'address',
        'city',
        'state',
        'country',
        'website',
        'use_case',
        'expected_monthly_volume',
        'api_key',
        'api_secret',
        'webhook_url',
        'api_enabled',
        'result_fetch_enabled',
        'paygo_result_reference_fetch_limit',
        'referral_code',
        'rate_limit',
        'allowed_ips',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'api_enabled' => 'boolean',
            'result_fetch_enabled' => 'boolean',
            'paygo_result_reference_fetch_limit' => 'integer',
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
        static::creating(function (Customer $customer) {
            if (blank($customer->referral_code)) {
                $customer->referral_code = static::generateReferralCode();
            }
        });

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
    public function wallet(): HasOneThrough
    {
        return $this->hasOneThrough(Wallet::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /**
     * Get all verification requests through the user.
     */
    public function verificationRequests(): HasManyThrough
    {
        return $this->hasManyThrough(VerificationRequest::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /**
     * Get all transactions through the user.
     */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /**
     * Get all branches for this customer.
     */
    public function branches(): HasManyThrough
    {
        return $this->hasManyThrough(Branch::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /**
     * Generate API credentials for the customer.
     */
    public function generateApiCredentials(): array
    {
        $this->api_key = 'ev_live_'.Str::random(32);
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

    public function hasResultFetchAccess(): bool
    {
        return $this->result_fetch_enabled !== false;
    }

    public function paygoResultReferenceFetchLimit(): int
    {
        return max(1, (int) ($this->paygo_result_reference_fetch_limit ?: 3));
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = 'EVR-'.strtoupper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function referralLink(?string $email = null): string
    {
        $path = '/result-pins/kit/'.$this->referral_code;

        if ($email) {
            $path .= '/'.rawurlencode($email);
        }

        return url($path);
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

    /**
     * Get dedicated virtual accounts through the user.
     */
    public function dedicatedVirtualAccounts(): HasMany
    {
        return $this->hasManyThrough(
            DedicatedVirtualAccount::class,
            User::class,
            'id',
            'user_id',
            'user_id',
            'id'
        );
    }

    /**
     * Get the active dedicated virtual account.
     */
    public function activeDedicatedAccount(): ?DedicatedVirtualAccount
    {
        return $this->dedicatedVirtualAccounts()
            ->where('active', true)
            ->first();
    }
}
