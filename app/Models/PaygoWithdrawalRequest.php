<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaygoWithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'paygo_wallet_id',
        'user_id',
        'paygo_wallet_transaction_id',
        'reference',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'metadata',
        'requested_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        return 'PGW-WD-'.Str::upper(Str::random(8)).'-'.time();
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PaygoWallet::class, 'paygo_wallet_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaygoWalletTransaction::class, 'paygo_wallet_transaction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
