<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaystackGatewayAccount extends Model
{
    use HasFactory;

    public const ENVIRONMENTS = ['test', 'live'];

    protected $fillable = [
        'owner_type',
        'customer_id',
        'label',
        'environment',
        'public_key',
        'secret_key',
        'key_fingerprint',
        'is_trusted',
        'is_active',
        'verification_status',
        'verified_at',
        'last_verified_at',
        'created_by_admin_id',
        'rotated_at',
        'metadata',
    ];

    protected $hidden = ['public_key', 'secret_key'];

    protected function casts(): array
    {
        return [
            'public_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'is_trusted' => 'boolean',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'rotated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function subaccounts(): HasMany
    {
        return $this->hasMany(CustomerPaystackSplitAccount::class);
    }

    public function isReady(): bool
    {
        return $this->owner_type === 'customer'
            && $this->is_trusted
            && $this->is_active
            && $this->verification_status === 'verified'
            && filled($this->secret_key);
    }

    public static function fingerprint(string $secretKey): string
    {
        return substr($secretKey, 0, 8).'...'.substr($secretKey, -4);
    }
}
