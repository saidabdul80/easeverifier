<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomerPaygoService extends Model
{
    use HasFactory;

    public const RESPONSE_MODES = ['redirect', 'json'];

    protected $fillable = [
        'user_id',
        'verification_service_id',
        'name',
        'public_slug',
        'verify_secret_hash',
        'price',
        'is_active',
        'success_url',
        'failure_url',
        'response_mode',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verificationService(): BelongsTo
    {
        return $this->belongsTo(VerificationService::class);
    }

    public function intents(): HasMany
    {
        return $this->hasMany(PaygoVerificationIntent::class);
    }

    public static function generatePublicSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'paygo';

        do {
            $slug = $base.'-'.Str::lower(Str::random(8));
        } while (static::where('public_slug', $slug)->exists());

        return $slug;
    }

    public static function generateSecret(): string
    {
        return 'pgs_'.Str::random(48);
    }

    public function rotateSecret(): string
    {
        $secret = static::generateSecret();
        $this->update(['verify_secret_hash' => hash('sha256', $secret)]);

        return $secret;
    }

    public function secretMatches(?string $secret): bool
    {
        return filled($secret) && hash_equals($this->verify_secret_hash, hash('sha256', $secret));
    }

    public function initiateUrl(): string
    {
        return route('paygo.initiate', $this->public_slug);
    }

    public function verifyUrl(): string
    {
        return url('/api/paygo/'.$this->public_slug.'/verify');
    }
}
