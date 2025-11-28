<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'secret_hash',
        'plain_secret',
        'environment',
        'is_active',
        'permissions',
        'allowed_ips',
        'rate_limit',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'permissions' => 'array',
            'allowed_ips' => 'array',
            'rate_limit' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'secret_hash',
    ];

    /**
     * Get the user that owns this API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API key pair.
     */
    public static function generate(int $userId, string $name = 'Default', string $environment = 'live'): self
    {
        $prefix = $environment === 'live' ? 'ev_live_' : 'ev_test_';
        $key = $prefix . Str::random(32);
        $secret = Str::random(48);

        return self::create([
            'user_id' => $userId,
            'name' => $name,
            'key' => $key,
            'secret_hash' => hash('sha256', $secret),
            'plain_secret' => $secret, // Will be shown once, then cleared
            'environment' => $environment,
            'is_active' => true,
            'rate_limit' => 100,
        ]);
    }

    /**
     * Get the bearer token (key:secret format).
     */
    public function getBearerToken(): ?string
    {
        if (!$this->plain_secret) {
            return null;
        }
        return base64_encode($this->key . ':' . $this->plain_secret);
    }

    /**
     * Validate a bearer token.
     */
    public static function validateBearer(string $bearerToken): ?self
    {
        $decoded = base64_decode($bearerToken, true);
        if (!$decoded || !str_contains($decoded, ':')) {
            // Try direct key lookup for simple bearer tokens
            return self::where('key', $bearerToken)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();
        }

        [$key, $secret] = explode(':', $decoded, 2);
        
        $apiKey = self::where('key', $key)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$apiKey || !hash_equals($apiKey->secret_hash, hash('sha256', $secret))) {
            return null;
        }

        return $apiKey;
    }

    /**
     * Mark the key as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Clear the plain secret after it's been shown.
     */
    public function clearPlainSecret(): void
    {
        $this->update(['plain_secret' => null]);
    }

    /**
     * Regenerate the secret.
     */
    public function regenerateSecret(): string
    {
        $secret = Str::random(48);
        $this->update([
            'secret_hash' => hash('sha256', $secret),
            'plain_secret' => $secret,
        ]);
        return $secret;
    }

    /**
     * Deactivate the key.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Check if IP is allowed.
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }
        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Scope for active keys.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}

