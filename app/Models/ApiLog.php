<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'verification_request_id',
        'direction',
        'endpoint',
        'method',
        'request_headers',
        'request_body',
        'response_status',
        'response_headers',
        'response_body',
        'response_time',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_body' => 'array',
            'response_headers' => 'array',
            'response_body' => 'array',
            'response_time' => 'integer',
        ];
    }

    /**
     * Get the user associated with this log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch associated with this log, if any.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the verification request associated with this log.
     */
    public function verificationRequest(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class);
    }

    /**
     * Scope for inbound logs.
     */
    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * Scope for outbound logs.
     */
    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public static function headerSummary(array $headers): array
    {
        $headerNames = collect($headers)
            ->keys()
            ->map(fn ($key) => strtolower((string) $key))
            ->reject(fn (string $key) => in_array($key, ['authorization', 'x-api-key', 'api-key', 'cookie', 'set-cookie'], true))
            ->sort()
            ->values()
            ->all();

        return array_filter([
            'header_count' => count($headers),
            'header_names' => $headerNames,
            'content_type' => static::firstHeaderValue($headers, 'content-type'),
            'accept' => static::firstHeaderValue($headers, 'accept'),
            'user_agent' => static::truncateValue(static::firstHeaderValue($headers, 'user-agent'), 120),
        ], fn ($value) => $value !== null && $value !== []);
    }

    public static function requestSummary(?array $payload): array
    {
        return static::payloadSummary($payload ?? [], includeOutcome: false);
    }

    public static function responseSummary(?array $payload, ?int $status = null): array
    {
        return array_filter(array_merge(
            ['http_status' => $status],
            static::payloadSummary($payload ?? [], includeOutcome: true),
        ), fn ($value) => $value !== null && $value !== []);
    }

    public static function exceptionSummary(string $message, string $code = 'EXCEPTION'): array
    {
        return [
            'success' => false,
            'code' => $code,
            'message' => static::truncateValue($message),
        ];
    }

    private static function payloadSummary(array $payload, bool $includeOutcome): array
    {
        $keys = collect(array_keys($payload))
            ->map(fn ($key) => (string) $key)
            ->reject(fn (string $key) => in_array(strtolower($key), ['api_key', 'branch'], true))
            ->values()
            ->all();

        $summary = [
            'keys' => $keys,
            'field_count' => count($keys),
            'approx_bytes' => strlen(json_encode($payload) ?: ''),
        ];

        if (! $includeOutcome) {
            return $summary;
        }

        $summary['success'] = static::successValue($payload);
        $summary['code'] = static::firstValue($payload, ['error_code', 'code', 'response_code', 'statusCode', 'status_code']);
        $summary['status'] = static::firstValue($payload, ['status', 'state']);
        $summary['message'] = static::truncateValue(static::firstValue($payload, ['message', 'error', 'error_message', 'detail']));

        if (isset($payload['subjects']) && is_array($payload['subjects'])) {
            $summary['subjects_count'] = count($payload['subjects']);
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $summary['data_keys'] = array_slice(array_keys($payload['data']), 0, 30);
        }

        return array_filter($summary, fn ($value) => $value !== null && $value !== []);
    }

    private static function firstValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return is_scalar($payload[$key]) ? $payload[$key] : json_encode($payload[$key]);
            }
        }

        return null;
    }

    private static function successValue(array $payload): ?bool
    {
        if (array_key_exists('success', $payload)) {
            return (bool) $payload['success'];
        }

        if (($payload['status'] ?? null) === 'success') {
            return true;
        }

        if (($payload['response_code'] ?? null) === '00') {
            return true;
        }

        return null;
    }

    private static function firstHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }

            if (is_array($value)) {
                return isset($value[0]) ? (string) $value[0] : null;
            }

            return (string) $value;
        }

        return null;
    }

    private static function truncateValue(mixed $value, int $limit = 300): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        return strlen($string) > $limit ? substr($string, 0, $limit) : $string;
    }
}
