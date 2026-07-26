<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ServiceProvider extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (self $provider): void {
            Cache::forget("service_providers:{$provider->verification_service_id}");

            $originalServiceId = $provider->getOriginal('verification_service_id');
            if ($originalServiceId && (int) $originalServiceId !== (int) $provider->verification_service_id) {
                Cache::forget("service_providers:{$originalServiceId}");
            }
        });

        static::deleted(function (self $provider): void {
            Cache::forget("service_providers:{$provider->verification_service_id}");
        });
    }

    protected $fillable = [
        'verification_service_id',
        'name',
        'base_url',
        'endpoint',
        'http_method',
        'auth_type',
        'auth_config',
        'request_headers',
        'request_body_template',
        'response_mapping',
        'timeout',
        'priority',
        'is_active',
        'environment',
    ];

    protected function casts(): array
    {
        return [
            'auth_config' => 'array',
            'request_headers' => 'array',
            'request_body_template' => 'array',
            'response_mapping' => 'array',
            'timeout' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope for test environment providers.
     */
    public function scopeTest($query)
    {
        return $query->where('environment', 'test');
    }

    /**
     * Scope for live environment providers.
     */
    public function scopeLive($query)
    {
        return $query->where('environment', 'live');
    }

    /**
     * Scope for specific environment.
     */
    public function scopeEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Get the verification service this provider belongs to.
     */
    public function verificationService(): BelongsTo
    {
        return $this->belongsTo(VerificationService::class);
    }

    /**
     * Get all verification requests handled by this provider.
     */
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    /**
     * Get the full endpoint URL.
     */
    public function getFullUrlAttribute(): string
    {
        return rtrim($this->base_url, '/') . '/' . ltrim($this->endpoint, '/');
    }

    /**
     * Build authentication headers/body based on auth_type.
     */
    public function buildAuthHeaders(): array
    {
        $authConfig = $this->normalizedAuthConfig();
        $headers = [];

        switch ($this->auth_type) {
            case 'bearer':
                $token = $authConfig['token'] ?? '';

                if (empty($token)) {
                    abort(400, 'Bearer token is required');
                }

                $headers['Authorization'] = 'Bearer ' . $token;

                break;

            case 'api_key_header':
                $headerName = $authConfig['header_name'] ?? 'X-API-Key';
                // Support both 'header_value' (from form) and 'api_key' (legacy)
                $apiKey = $authConfig['header_value'] ?? $authConfig['api_key'] ?? '';
                if (!empty($apiKey)) {
                    $headers[$headerName] = $apiKey;
                }
                break;

            case 'basic':
                $username = $authConfig['username'] ?? '';
                $password = $authConfig['password'] ?? '';
                $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
                break;

            case 'custom':
                // Custom headers from auth_config
                foreach ($authConfig['headers'] ?? [] as $key => $value) {
                    $headers[$key] = $value;
                }
                break;
        }

        Log::info('buildAuthHeaders', [
            'provider_id' => $this->id,
            'auth_type' => $this->auth_type,
            'header_names' => array_keys($headers),
        ]);

        return $headers;
    }

    /**
     * Build request body with the search parameter.
     */
    public function buildRequestBody(string $searchParameter): array
    {
        $authConfig = $this->normalizedAuthConfig();
        $body = $this->request_body_template ?? [];

        // Replace placeholder with actual search parameter
        array_walk_recursive($body, function (&$value) use ($searchParameter) {
            if ($value === '{{search_parameter}}') {
                $value = $searchParameter;
            }
        });

        // Add API key to body if auth type requires it
        if ($this->auth_type === 'api_key_body') {
            $keyName = $authConfig['key_name'] ?? 'api_key';
            // Support both 'key_value' (from form) and 'api_key' (legacy)
            $body[$keyName] = $authConfig['key_value'] ?? $authConfig['api_key'] ?? '';
        }

        return $body;
    }

    /**
     * Normalize auth config so runtime can handle both legacy and UI-saved shapes.
     */
    public function normalizedAuthConfig(): array
    {
        $config = is_array($this->auth_config) ? $this->auth_config : [];

        if ($this->auth_type !== 'custom') {
            return $config;
        }

        if (isset($config['headers']) && is_array($config['headers'])) {
            return $config;
        }

        $customHeaders = $config['custom_headers'] ?? null;

        if (is_array($customHeaders)) {
            $config['headers'] = $customHeaders;
            return $config;
        }

        if (is_string($customHeaders) && trim($customHeaders) !== '') {
            $decoded = json_decode($customHeaders, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $config['headers'] = $decoded;
            }
        }

        return $config;
    }

    /**
     * Scope for active providers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
