<?php

namespace App\Models;

use App\Notifications\VerifyEmailOtpNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verification_otp',
        'email_verification_otp_expires_at',
        'password',
        'phone',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'email_verification_otp',
        'email_verification_otp_expires_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is customer.
     */
    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    /**
     * Get the customer profile for the user.
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Get the wallet for the user.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->whereNull('branch_id');
    }

    /**
     * Get all wallets for the user, including branch wallets.
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * Get all transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function resultPinOrders(): HasMany
    {
        return $this->hasMany(ResultPinOrder::class);
    }

    public function resultPinPricing(): HasMany
    {
        return $this->hasMany(CustomerResultPinPricing::class);
    }

    /**
     * Get all verification requests for the user.
     */
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    /**
     * Get custom pricing for the user.
     */
    public function customPricing(): HasMany
    {
        return $this->hasMany(CustomerServicePricing::class);
    }

    /**
     * Get all API keys for the user.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * Get the user's branches.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the price for a specific service.
     */
    public function getPriceForService(VerificationService $service): float
    {
        $customPrice = $this->customPricing()
            ->where('verification_service_id', $service->id)
            ->where('is_active', true)
            ->first();

        return $customPrice ? $customPrice->price : $service->default_price;
    }

    public function hasResultFetchAccess(): bool
    {
        return $this->customer?->hasResultFetchAccess() ?? true;
    }

    public function sendEmailVerificationNotification(): void
    {
        $otp = $this->generateEmailVerificationOtp();

        try {
            $this->notify(new VerifyEmailOtpNotification($otp));
        } catch (\Throwable $exception) {
            Log::error('Failed to send email verification OTP.', [
                'user_id' => $this->id,
                'email' => $this->email,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function generateEmailVerificationOtp(): string
    {
        $otp = (string) random_int(100000, 999999);

        $this->forceFill([
            'email_verification_otp' => Hash::make($otp),
            'email_verification_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        return $otp;
    }

    public function verifyEmailOtp(string $otp): bool
    {
        if (
            blank($this->email_verification_otp) ||
            ! $this->email_verification_otp_expires_at ||
            now()->greaterThan($this->email_verification_otp_expires_at) ||
            ! Hash::check($otp, $this->email_verification_otp)
        ) {
            return false;
        }

        $this->markEmailAsVerified();
        $this->clearEmailVerificationOtp();

        return true;
    }

    public function clearEmailVerificationOtp(): void
    {
        $this->forceFill([
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ])->save();
    }
}
