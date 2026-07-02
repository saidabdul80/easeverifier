<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaygoWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'paygo_wallet_id',
        'user_id',
        'reference',
        'type',
        'category',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'metadata',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public static function generateReference(): string
    {
        return 'PGW-'.Str::upper(Str::random(10)).'-'.time();
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PaygoWallet::class, 'paygo_wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
