<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->services($now) as $service) {
            DB::table('verification_services')->updateOrInsert(
                ['slug' => $service['slug']],
                $service,
            );
        }
    }

    public function down(): void
    {
        DB::table('verification_services')
            ->whereIn('slug', collect($this->services(now()))->pluck('slug')->all())
            ->delete();
    }

    private function services($now): array
    {
        return [
            [
                'name' => 'WAEC Result Form',
                'slug' => 'waec-result-form',
                'description' => 'Retrieve WAEC result checker form metadata and supported input fields.',
                'icon' => 'mdi-form-select',
                'default_price' => 0.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'WAEC Result Fetch',
                'slug' => 'waec-result-fetch',
                'description' => 'Fetch and parse WAEC candidate result details from supplied checker credentials.',
                'icon' => 'mdi-certificate',
                'default_price' => 100.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 21,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'NECO Result Form',
                'slug' => 'neco-result-form',
                'description' => 'Retrieve NECO result checker form metadata and supported input fields.',
                'icon' => 'mdi-form-select',
                'default_price' => 0.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 22,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'NECO Result Fetch',
                'slug' => 'neco-result-fetch',
                'description' => 'Fetch and parse NECO candidate result details from supplied checker credentials.',
                'icon' => 'mdi-certificate',
                'default_price' => 100.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 23,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'NECO e-Verify Result Form',
                'slug' => 'neco-everify-result-form',
                'description' => 'Retrieve NECO e-Verify result verification form metadata and supported input fields.',
                'icon' => 'mdi-form-select',
                'default_price' => 0.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 24,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'NECO e-Verify Result Fetch',
                'slug' => 'neco-everify-result-fetch',
                'description' => 'Fetch and parse NECO e-Verify candidate result details using verification token and payment reference.',
                'icon' => 'mdi-certificate-search',
                'default_price' => 100.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 25,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'NBAIS Result Form',
                'slug' => 'nbais-result-form',
                'description' => 'Retrieve NBAIS result checker form metadata and supported input fields.',
                'icon' => 'mdi-form-select',
                'default_price' => 0.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 26,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'NBAIS Result Fetch',
                'slug' => 'nbais-result-fetch',
                'description' => 'Fetch and parse NBAIS candidate result details from supplied examination details.',
                'icon' => 'mdi-certificate',
                'default_price' => 100.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 27,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'NABTEB Result Form',
                'slug' => 'nabteb-result-form',
                'description' => 'Retrieve NABTEB eWorld result checker form metadata and supported input fields.',
                'icon' => 'mdi-form-select',
                'default_price' => 0.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 28,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'NABTEB Result Fetch',
                'slug' => 'nabteb-result-fetch',
                'description' => 'Fetch and parse NABTEB eWorld result details from supplied checker credentials.',
                'icon' => 'mdi-certificate',
                'default_price' => 100.00,
                'cost_price' => 0.00,
                'is_active' => true,
                'sort_order' => 29,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
};
