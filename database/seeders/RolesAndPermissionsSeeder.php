<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Customer management
            'view customers',
            'create customers',
            'edit customers',
            'delete customers',

            // Wallet management
            'view wallets',
            'credit wallets',
            'debit wallets',

            // Transaction management
            'view transactions',
            'view all transactions',

            // Verification services
            'view services',
            'create services',
            'edit services',
            'delete services',

            // Service providers
            'view providers',
            'create providers',
            'edit providers',
            'delete providers',

            // Pricing
            'view pricing',
            'set pricing',

            // Verification requests
            'perform verification',
            'view verification history',
            'view all verifications',

            // API management
            'manage api keys',
            'view api logs',

            // Settings
            'manage settings',

            // Reports
            'view reports',
            'export reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $customerRole = Role::firstOrCreate(['name' => 'customer']);
        $customerRole->givePermissionTo([
            'perform verification',
            'view verification history',
            'view transactions',
            'view wallets',
            'manage api keys',
        ]);
    }
}

