<?php

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;

it('builds name, full name and initials', function () {
    $user = User::factory()->make(['first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz']);

    expect($user->name)->toBe('Juan Dela Cruz')
        ->and($user->full_name)->toBe('Juan Santos Dela Cruz')
        ->and($user->initials)->toBe('JD');
});

it('casts role and status to enums and hashes passwords', function () {
    $user = User::factory()->admin()->create(['password' => 'secret123']);

    expect($user->role)->toBe(Role::Admin)
        ->and($user->status)->toBe(AccountStatus::Active)
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->isIntern())->toBeFalse()
        ->and(password_verify('secret123', $user->password))->toBeTrue()
        ->and($user->member_no)->toStartWith('ADM-');
});

it('creates an intern profile for intern users', function () {
    $user = User::factory()->intern()->create();

    expect($user->internProfile)->not->toBeNull()
        ->and($user->internProfile->student_number)->not->toBeEmpty()
        ->and($user->member_no)->toStartWith('INT-');
});

it('creates a registered, approved company for company users', function () {
    $user = User::factory()->company()->create();

    expect($user->company)->not->toBeNull()
        ->and($user->company->isRegistered())->toBeTrue()
        ->and($user->company->isApproved())->toBeTrue()
        ->and($user->company->company_code)->toHaveLength(8);
});

it('treats companies without a login as partners', function () {
    $partner = Company::factory()->partner()->create();

    expect($partner->isPartner())->toBeTrue()
        ->and($partner->user)->toBeNull()
        ->and(Company::partners()->count())->toBe(1)
        ->and(Company::registered()->count())->toBe(0);
});

it('scopes users by role and status', function () {
    User::factory()->intern()->count(2)->create();
    User::factory()->adviser()->disabled()->create();

    expect(User::ofRole(Role::Intern)->count())->toBe(2)
        ->and(User::active()->count())->toBe(2)
        ->and(User::disabled()->count())->toBe(1)
        ->and(User::disabled()->first()->isDisabled())->toBeTrue();
});
