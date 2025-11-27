<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProvider extends Model
{
    use HasFactory;

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
        $headers = [];

        switch ($this->auth_type) {
            case 'bearer':
                $token = $this->auth_config['token'] ?? '';
                $headers['Authorization'] = 'Bearer ' . $token;
                break;

            case 'api_key_header':
                $headerName = $this->auth_config['header_name'] ?? 'X-API-Key';
                $apiKey = $this->auth_config['api_key'] ?? '';
                $headers[$headerName] = $apiKey;
                break;

            case 'basic':
                $username = $this->auth_config['username'] ?? '';
                $password = $this->auth_config['password'] ?? '';
                $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
                break;

            case 'custom':
                // Custom headers from auth_config
                foreach ($this->auth_config['headers'] ?? [] as $key => $value) {
                    $headers[$key] = $value;
                }
                break;
        }

        return $headers;
    }

    /**
     * Build request body with the search parameter.
     */
    public function buildRequestBody(string $searchParameter): array
    {
        $body = $this->request_body_template ?? [];

        // Replace placeholder with actual search parameter
        array_walk_recursive($body, function (&$value) use ($searchParameter) {
            if ($value === '{{search_parameter}}') {
                $value = $searchParameter;
            }
        });

        // Add API key to body if auth type requires it
        if ($this->auth_type === 'api_key_body') {
            $keyName = $this->auth_config['key_name'] ?? 'api_key';
            $body[$keyName] = $this->auth_config['api_key'] ?? '';
        }

        return $body;
    }

    /**
     * Scope for active providers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

