<?php

namespace Database\Factories;

use App\Models\PaygoPaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaygoPaymentAttempt>
 */
class PaygoPaymentAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attempt_number' => 0,
            'reference' => 'PGO-'.fake()->unique()->bothify('??????????-##########'),
            'status' => 'initialized',
            'paystack_gateway_owner_type' => 'system',
            'amount' => 500,
            'system_price_snapshot' => 300,
            'initialized_at' => now(),
        ];
    }
}
