<?php

use App\Actions\ImportAdvisers;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates advisers and emails credentials', function () {
    Notification::fake();

    $result = app(ImportAdvisers::class)(collect([
        ['first_name' => 'Elsie', 'middle_name' => null, 'last_name' => 'Isip', 'email' => 'Elsie@Example.com', 'phone' => '0918', '_row' => 2],
        ['first_name' => 'Ramon', 'middle_name' => 'D', 'last_name' => 'Cruz', 'email' => 'ramon@example.com', 'phone' => null, '_row' => 3],
    ]));

    expect($result->failed())->toBeFalse()->and($result->created)->toBe(2);
    $elsie = User::where('email', 'elsie@example.com')->firstOrFail();
    expect($elsie->role)->toBe(Role::Adviser)->and($elsie->member_no)->toStartWith('ADV-');
    Notification::assertSentTo($elsie, AccountCredentials::class);
});

it('fails on duplicate or taken emails and missing names without creating anything', function () {
    Notification::fake();
    User::factory()->intern()->create(['email' => 'taken@example.com']);

    $result = app(ImportAdvisers::class)(collect([
        ['first_name' => 'A', 'last_name' => 'B', 'email' => 'dup@example.com', '_row' => 2],
        ['first_name' => 'C', 'last_name' => 'D', 'email' => 'DUP@example.com', '_row' => 3],
        ['first_name' => 'E', 'last_name' => null, 'email' => 'taken@example.com', '_row' => 4],
    ]));

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->errors))->toBe([3, 4])
        ->and(User::ofRole(Role::Adviser)->count())->toBe(0);
    Notification::assertNothingSent();
});
