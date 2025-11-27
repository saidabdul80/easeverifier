<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VerificationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'verification_service_id',
        'service_provider_id',
        'transaction_id',
        'reference',
        'search_parameter',
        'request_data',
        'response_data',
        'amount_charged',
        'status',
        'source',
        'ip_address',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'response_data' => 'array',
            'amount_charged' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Generate a unique reference.
     */
    public static function generateReference(): string
    {
        return 'VER-' . strtoupper(Str::random(8)) . '-' . time();
    }

    /**
     * Get the user that owns this request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the verification service.
     */
    public function verificationService(): BelongsTo
    {
        return $this->belongsTo(VerificationService::class);
    }

    /**
     * Get the service provider used.
     */
    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    /**
     * Get the associated transaction.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Mark the request as completed.
     */
    public function markAsCompleted(array $responseData): void
    {
        $this->update([
            'status' => 'completed',
            'response_data' => $responseData,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the request as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Scope for pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed requests.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed requests.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Check if request is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }
}

