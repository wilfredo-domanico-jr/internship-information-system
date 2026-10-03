<?php

use App\Actions\CreateAdviser;
use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Notifications\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates an active adviser and emails a working temporary password', function () {
    Notification::fake();

    $user = app(CreateAdviser::class)(['first_name' => 'Elsie', 'last_name' => 'Isip', 'email' => 'elsie@example.com', 'phone' => '09170000000']);

    expect($user->role)->toBe(Role::Adviser)
        ->and($user->status)->toBe(AccountStatus::Active)
        ->and($user->member_no)->toStartWith('ADV-');

    Notification::assertSentTo($user, AccountCredentials::class, function (AccountCredentials $n) use ($user) {
        expect(strlen($n->plainPassword))->toBe(12)
            ->and(password_verify($n->plainPassword, $user->password))->toBeTrue();
        $mail = $n->toMail($user);
        expect($mail->subject)->toContain(config('wiis.name'))
            ->and(implode("\n", $mail->introLines))->toContain($user->email)->toContain($n->plainPassword);

        return true;
    });
});
