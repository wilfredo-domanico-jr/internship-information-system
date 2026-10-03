<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\MemberNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates sequential member numbers per role and year', function () {
    $generator = app(MemberNumberGenerator::class);
    $year = now()->year;

    expect($generator->generate(Role::Intern))->toBe("INT-{$year}-00001");

    User::factory()->intern()->create(['member_no' => "INT-{$year}-00001"]);

    expect($generator->generate(Role::Intern))->toBe("INT-{$year}-00002")
        ->and($generator->generate(Role::Adviser))->toBe("ADV-{$year}-00001");
});

it('skips numbers that are already taken', function () {
    $generator = app(MemberNumberGenerator::class);
    $year = now()->year;
    User::factory()->admin()->create(['member_no' => "ADM-{$year}-00002"]);

    // One admin exists, so the next candidate would be 00002, which is taken.
    expect($generator->generate(Role::Admin))->toBe("ADM-{$year}-00003");
});
