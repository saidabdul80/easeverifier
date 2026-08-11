<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class VerificationRequest extends Model
{
    use HasFactory;

    private bool $sourceWasAssigned = false;

    private ?string $pendingSourceOverride = null;

    protected $fillable = [
        'user_id',
        'branch_id',
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

    protected function source(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($this->relationLoaded('sourceOverride')) {
                    return $this->sourceOverride?->source ?? $value;
                }

                if (! $this->exists) {
                    return $this->pendingSourceOverride ?? $value;
                }

                return $this->sourceOverride()->value('source') ?? $value;
            },
            set: function (?string $value): array {
                $this->sourceWasAssigned = true;
                $this->pendingSourceOverride = $value === 'paygo' ? 'paygo' : null;

                return [
                    'source' => $value === 'paygo' ? 'api' : $value,
                ];
            },
        );
    }

    protected static function booted(): void
    {
        static::saved(function (VerificationRequest $request): void {
            if (! $request->sourceWasAssigned) {
                return;
            }

            if ($request->pendingSourceOverride) {
                $override = $request->sourceOverride()->updateOrCreate(
                    [],
                    ['source' => $request->pendingSourceOverride],
                );

                $request->setRelation('sourceOverride', $override);
            } else {
                $request->sourceOverride()->delete();
                $request->unsetRelation('sourceOverride');
            }

            $request->sourceWasAssigned = false;
        });
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
     * Get the branch that initiated this verification, if any.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function sourceOverride(): HasOne
    {
        return $this->hasOne(VerificationRequestSourceOverride::class);
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

    /**
     * Determine whether this stored response is still safe to reuse.
     */
    public function canReuseResponseData(): bool
    {
        if ($this->status !== 'completed' || empty($this->response_data) || ! $this->completed_at) {
            return false;
        }

        if (! $this->service_provider_id) {
            return false;
        }

        $provider = $this->relationLoaded('serviceProvider')
            ? $this->serviceProvider
            : $this->serviceProvider()->first();

        if (! $provider) {
            return false;
        }

        return ! $provider->updated_at || $provider->updated_at->lte($this->completed_at);
    }
}
