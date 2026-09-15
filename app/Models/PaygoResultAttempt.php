<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaygoResultAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'paygo_verification_intent_id',
        'verification_request_id',
        'lookup_hash',
        'lookup_label',
        'payload',
        'status',
        'attempt_number',
        'success_counted',
        'error_code',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'attempt_number' => 'integer',
            'success_counted' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaygoVerificationIntent::class, 'paygo_verification_intent_id');
    }

    public function verificationRequest(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class);
    }
}
