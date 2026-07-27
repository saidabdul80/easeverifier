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

    public function scopeOrderedByResultBoard($query)
    {
        return $query->orderBy('name');
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

    public static function syncResultBoardSetForUser(User $user): void
    {
        if (! $user->hasResultFetchAccess()) {
            return;
        }

        $resultServices = VerificationService::active()
            ->where('slug', 'like', '%-result-fetch')
            ->ordered()
            ->get();

        if ($resultServices->isEmpty()) {
            return;
        }

        $existingServices = static::query()
            ->with('verificationService')
            ->where('user_id', $user->id)
            ->whereHas('verificationService', fn ($query) => $query->where('slug', 'like', '%-result-fetch'))
            ->get()
            ->keyBy('verification_service_id');

        if ($existingServices->isEmpty()) {
            return;
        }

        $template = $existingServices->firstWhere('is_active', true) ?: $existingServices->first();
        $publicPrice = max(
            (float) $template->price,
            (float) $resultServices->map(fn (VerificationService $service) => $user->getPriceForService($service))->max() + 1,
        );

        foreach ($resultServices as $service) {
            if ($existingServices->has($service->id)) {
                continue;
            }

            $board = strtoupper(preg_replace('/-result-fetch$/', '', $service->slug));

            static::create([
                'user_id' => $user->id,
                'verification_service_id' => $service->id,
                'name' => $board.' Result Verification',
                'public_slug' => static::generatePublicSlug($board.' Result Verification'),
                'verify_secret_hash' => hash('sha256', static::generateSecret()),
                'price' => $publicPrice,
                'is_active' => $template->is_active,
                'success_url' => $template->success_url,
                'failure_url' => $template->failure_url,
                'response_mode' => $template->response_mode ?? 'redirect',
            ]);
        }
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

    public function resultUrl(): string
    {
        return route('paygo.results.service', $this->public_slug);
    }

    public function resultSelectorUrl(): ?string
    {
        $referralCode = $this->user?->customer?->referral_code;

        return $referralCode ? route('paygo.results.customer', $referralCode) : null;
    }

    public function isResultVerification(): bool
    {
        return (bool) preg_match('/^[a-z0-9-]+-result-fetch$/', (string) $this->verificationService?->slug);
    }

    public function resultBoard(): ?string
    {
        if (! $this->isResultVerification()) {
            return null;
        }

        return preg_replace('/-result-fetch$/', '', (string) $this->verificationService->slug);
    }
}
