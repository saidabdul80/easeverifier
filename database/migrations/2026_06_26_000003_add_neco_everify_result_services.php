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
            ->whereIn('slug', ['neco-everify-result-form', 'neco-everify-result-fetch'])
            ->delete();
    }

    private function services($now): array
    {
        return [
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
        ];
    }
};
