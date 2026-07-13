<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('customer');
});

test('admin can impersonate a customer and return to admin account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($admin)
        ->get(route('impersonate', $customer->id))
        ->assertRedirect(route('customer.dashboard'));

    $this->assertAuthenticatedAs($customer);

    $this->get(route('impersonate.leave'))
        ->assertRedirect(route('admin.customers.index'));

    $this->assertAuthenticatedAs($admin);
});

test('customer cannot impersonate another customer', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $target = User::factory()->create();
    $target->assignRole('customer');

    $this->actingAs($customer)
        ->get(route('impersonate', $target->id))
        ->assertForbidden();

    $this->assertAuthenticatedAs($customer);
});
