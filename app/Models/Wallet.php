<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'bonus_balance',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'bonus_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get total available balance (balance + bonus).
     */
    public function getTotalBalanceAttribute(): float
    {
        return $this->balance + $this->bonus_balance;
    }

    /**
     * Check if wallet has sufficient funds.
     */
    public function hasSufficientFunds(float $amount): bool
    {
        return $this->total_balance >= $amount;
    }

    /**
     * Credit the wallet.
     */
    public function credit(float $amount, string $category, string $description, array $metadata = []): Transaction
    {
        return DB::transaction(function () use ($amount, $category, $description, $metadata) {
            $this->lockForUpdate();

            $balanceBefore = $this->balance;
            $this->balance += $amount;
            $this->save();

            return $this->transactions()->create([
                'user_id' => $this->user_id,
                'reference' => Transaction::generateReference(),
                'type' => 'credit',
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'description' => $description,
                'metadata' => $metadata,
                'status' => 'completed',
            ]);
        });
    }

    /**
     * Debit the wallet.
     */
    public function debit(float $amount, string $category, string $description, array $metadata = []): Transaction
    {
        return DB::transaction(function () use ($amount, $category, $description, $metadata) {
            $this->lockForUpdate();

            if (!$this->hasSufficientFunds($amount)) {
                throw new \Exception('Insufficient funds');
            }

            $balanceBefore = $this->balance;
            
            // First deduct from bonus balance if available
            if ($this->bonus_balance > 0) {
                $bonusDeduction = min($this->bonus_balance, $amount);
                $this->bonus_balance -= $bonusDeduction;
                $amount -= $bonusDeduction;
            }
            
            // Then deduct remaining from main balance
            $this->balance -= $amount;
            $this->save();

            return $this->transactions()->create([
                'user_id' => $this->user_id,
                'reference' => Transaction::generateReference(),
                'type' => 'debit',
                'category' => $category,
                'amount' => $balanceBefore - $this->balance,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'description' => $description,
                'metadata' => $metadata,
                'status' => 'completed',
            ]);
        });
    }
}

