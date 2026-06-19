<?php

namespace App\Actions\Fortify;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'account_type' => ['required', Rule::in(Customer::ACCOUNT_TYPES)],
            'registration_number' => ['nullable', 'string', 'max:255', Rule::requiredIf(($input['account_type'] ?? null) === 'business')],
            'address' => ['nullable', 'string', Rule::requiredIf(($input['account_type'] ?? null) === 'business')],
            'website' => ['nullable', 'url', 'max:255', Rule::requiredIf(($input['account_type'] ?? null) === 'business')],
            'use_case' => ['nullable', 'string', Rule::requiredIf(($input['account_type'] ?? null) === 'business')],
            'expected_monthly_volume' => [
                'nullable',
                'string',
                Rule::in(Customer::EXPECTED_MONTHLY_VOLUMES),
                Rule::requiredIf(($input['account_type'] ?? null) === 'business'),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'is_active' => true,
            ]);

            // Assign customer role
            Role::findOrCreate('customer');
            $user->assignRole('customer');

            // Create customer profile (wallet is auto-created via Customer model boot)
            Customer::create([
                'user_id' => $user->id,
                'account_type' => $input['account_type'],
                'registration_number' => $input['account_type'] === 'business' ? $input['registration_number'] : null,
                'address' => $input['account_type'] === 'business' ? $input['address'] : null,
                'website' => $input['account_type'] === 'business' ? $input['website'] : null,
                'use_case' => $input['account_type'] === 'business' ? $input['use_case'] : null,
                'expected_monthly_volume' => $input['account_type'] === 'business' ? $input['expected_monthly_volume'] : null,
                'api_enabled' => false,
                'rate_limit' => 100,
            ]);

            return $user;
        });
    }
}
