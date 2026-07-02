<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PaygoWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'pending_withdrawal',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'pending_withdrawal' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaygoWalletTransaction::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(PaygoWithdrawalRequest::class);
    }

    public function credit(float $amount, string $description, array $metadata = []): ?PaygoWalletTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($amount, $description, $metadata) {
            $wallet = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = $wallet->balance;
            $wallet->balance += $amount;
            $wallet->save();

            $this->balance = $wallet->balance;

            return $wallet->transactions()->create([
                'user_id' => $wallet->user_id,
                'reference' => PaygoWalletTransaction::generateReference(),
                'type' => 'credit',
                'category' => 'earning',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'metadata' => $metadata,
                'status' => 'completed',
            ]);
        });
    }

    public function requestWithdrawal(float $amount, array $bankDetails): PaygoWithdrawalRequest
    {
        return DB::transaction(function () use ($amount, $bankDetails) {
            $wallet = static::whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($amount <= 0 || $wallet->balance < $amount) {
                throw new \RuntimeException('Insufficient PayGo wallet balance.');
            }

            $balanceBefore = $wallet->balance;
            $wallet->balance -= $amount;
            $wallet->pending_withdrawal += $amount;
            $wallet->save();

            $transaction = $wallet->transactions()->create([
                'user_id' => $wallet->user_id,
                'reference' => PaygoWalletTransaction::generateReference(),
                'type' => 'debit',
                'category' => 'withdrawal',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => 'PayGo wallet withdrawal request',
                'metadata' => $bankDetails,
                'status' => 'pending',
            ]);

            return $wallet->withdrawalRequests()->create([
                'user_id' => $wallet->user_id,
                'paygo_wallet_transaction_id' => $transaction->id,
                'reference' => PaygoWithdrawalRequest::generateReference(),
                'amount' => $amount,
                'bank_name' => $bankDetails['bank_name'],
                'account_number' => $bankDetails['account_number'],
                'account_name' => $bankDetails['account_name'],
                'status' => 'pending',
                'requested_at' => now(),
            ]);
        });
    }
}
