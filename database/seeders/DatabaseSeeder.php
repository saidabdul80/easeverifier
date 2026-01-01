<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@verify.ashlabtech.ng'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Create a test customer
        $customerUser = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Test Customer',
                'password' => Hash::make('password'),
                'phone' => '08012345678',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        if (!$customerUser->hasRole('customer')) {
            $customerUser->assignRole('customer');
        }

        // Create customer profile (wallet is auto-created via Customer model boot)
        Customer::firstOrCreate(
            ['user_id' => $customerUser->id],
            [
                'company_name' => 'Test Company Ltd',
                'business_type' => 'Technology',
                'address' => '123 Test Street',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'api_enabled' => true,
                'rate_limit' => 100,
            ]
        );

        // Update wallet with initial balance if it exists
        if ($customerUser->wallet) {
            $customerUser->wallet->update([
                'balance' => 10000.00,
                'bonus_balance' => 500.00,
            ]);
        }

        // Seed verification services
        $this->call(VerificationServicesSeeder::class);
    }
}
