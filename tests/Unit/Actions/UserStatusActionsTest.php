<?php

use App\Actions\DisableUser;
use App\Actions\ReactivateUser;
use App\Enums\AccountStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('disables and reactivates a non-admin account', function () {
    $admin = User::factory()->admin()->create();
    $intern = User::factory()->intern()->create();

    app(DisableUser::class)($intern, $admin);
    expect($intern->refresh()->status)->toBe(AccountStatus::Disabled);

    app(ReactivateUser::class)($intern);
    expect($intern->refresh()->status)->toBe(AccountStatus::Active);
});

it('refuses to disable an admin or yourself', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    expect(fn () => app(DisableUser::class)($otherAdmin, $admin))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DisableUser::class)($admin, $admin))->toThrow(DomainRuleViolation::class);
    expect($otherAdmin->refresh()->status)->toBe(AccountStatus::Active);
});
