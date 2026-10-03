<?php

use App\Actions\UpdateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates shared and intern fields', function () {
    $user = User::factory()->intern()->create();

    app(UpdateProfile::class)($user, [
        'first_name' => 'Juan', 'last_name' => 'Cruz', 'phone' => '0917',
        'gender' => 'Male', 'birthdate' => '2003-05-10', 'about' => 'Hello.',
    ]);

    $user->refresh();
    expect($user->first_name)->toBe('Juan')
        ->and($user->phone)->toBe('0917')
        ->and($user->internProfile->gender)->toBe('Male')
        ->and($user->internProfile->about)->toBe('Hello.');
});

it('updates company fields for company users', function () {
    $user = User::factory()->company()->create();

    app(UpdateProfile::class)($user, [
        'first_name' => 'Marco', 'last_name' => 'V',
        'company_name' => 'NewCo', 'company_type' => 'Retail', 'website' => 'https://newco.example',
        'address' => 'Makati', 'about' => 'About us.',
    ]);

    $company = $user->company->fresh();
    expect($company->name)->toBe('NewCo')
        ->and($company->type)->toBe('Retail')
        ->and($company->address)->toBe('Makati')
        ->and($company->about)->toBe('About us.');
});

it('never writes intern data onto a company user', function () {
    $user = User::factory()->company()->create();

    app(UpdateProfile::class)($user, [
        'first_name' => 'Marco', 'last_name' => 'V',
        'company_name' => $user->company->name, 'company_type' => 'Retail', 'address' => 'Makati',
        'gender' => 'Male', 'birthdate' => '2003-05-10',
    ]);

    expect($user->fresh()->internProfile)->toBeNull();
});
