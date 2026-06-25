<?php

namespace Database\Seeders;

use App\Models\ResultPinProduct;
use Illuminate\Database\Seeder;

class ResultPinProductSeeder extends Seeder
{
    public function run(): void
    {
        ResultPinProduct::updateOrCreate(
            [
                'provider' => 'naijaresultpins',
                'provider_card_type_id' => '1',
            ],
            [
                'name' => 'WAEC Scratch Card',
                'slug' => 'waec-scratch-card',
                'board' => 'waec',
                'description' => 'WAEC result checker scratch card PIN and serial number.',
                'price' => 3500.00,
                'cost_price' => 3340.00,
                'min_quantity' => 1,
                'max_quantity' => 100,
                'is_active' => true,
                'sort_order' => 1,
                'metadata' => [
                    'source' => 'provider_documentation',
                    'provider' => 'NaijaResultPins',
                ],
            ],
        );
    }
}
