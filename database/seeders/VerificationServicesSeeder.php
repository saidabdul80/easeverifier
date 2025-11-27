<?php

namespace Database\Seeders;

use App\Models\VerificationService;
use App\Models\ServiceProvider;
use Illuminate\Database\Seeder;

class VerificationServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create NIN Verification Service
        $ninService = VerificationService::create([
            'name' => 'NIN Verification',
            'slug' => 'nin',
            'description' => 'Verify National Identification Number (NIN) to retrieve personal details including name, date of birth, and photo.',
            'icon' => 'mdi-card-account-details',
            'default_price' => 100.00,
            'cost_price' => 50.00,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create a sample provider for NIN (this would be configured with actual API details)
        ServiceProvider::create([
            'verification_service_id' => $ninService->id,
            'name' => 'NIMC Direct API',
            'base_url' => 'https://api.example.com',
            'endpoint' => '/v1/nin/verify',
            'http_method' => 'POST',
            'auth_type' => 'bearer',
            'auth_config' => [
                'token' => 'your-api-token-here',
            ],
            'request_headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'request_body_template' => [
                'nin' => '{{search_parameter}}',
            ],
            'response_mapping' => [
                'first_name' => 'data.firstName',
                'last_name' => 'data.lastName',
                'middle_name' => 'data.middleName',
                'date_of_birth' => 'data.dateOfBirth',
                'gender' => 'data.gender',
                'phone' => 'data.phone',
                'photo' => 'data.photo',
            ],
            'timeout' => 30,
            'priority' => 1,
            'is_active' => true,
        ]);

        // Create BVN Verification Service (for future use)
        $bvnService = VerificationService::create([
            'name' => 'BVN Verification',
            'slug' => 'bvn',
            'description' => 'Verify Bank Verification Number (BVN) to retrieve banking and personal information.',
            'icon' => 'mdi-bank',
            'default_price' => 150.00,
            'cost_price' => 80.00,
            'is_active' => false, // Not yet active
            'sort_order' => 2,
        ]);

        // Create CAC Verification Service (for future use)
        $cacService = VerificationService::create([
            'name' => 'CAC Verification',
            'slug' => 'cac',
            'description' => 'Verify Corporate Affairs Commission (CAC) registration details for businesses.',
            'icon' => 'mdi-domain',
            'default_price' => 200.00,
            'cost_price' => 100.00,
            'is_active' => false, // Not yet active
            'sort_order' => 3,
        ]);

        // Create Drivers License Verification Service (for future use)
        VerificationService::create([
            'name' => 'Driver\'s License Verification',
            'slug' => 'drivers-license',
            'description' => 'Verify Nigerian Driver\'s License to retrieve license holder information.',
            'icon' => 'mdi-card-account-details-outline',
            'default_price' => 120.00,
            'cost_price' => 60.00,
            'is_active' => false,
            'sort_order' => 4,
        ]);

        // Create Voter's Card Verification Service (for future use)
        VerificationService::create([
            'name' => 'Voter\'s Card Verification',
            'slug' => 'voters-card',
            'description' => 'Verify INEC Voter\'s Card to retrieve voter registration details.',
            'icon' => 'mdi-vote',
            'default_price' => 100.00,
            'cost_price' => 50.00,
            'is_active' => false,
            'sort_order' => 5,
        ]);
    }
}

