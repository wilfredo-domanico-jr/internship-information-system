<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('shows the forgot password form', function () {
    $this->get('/password/forgot')->assertOk()->assertSee('Reset');
});

it('emails a reset link to a known address', function () {
    Notification::fake();
    $user = User::factory()->intern()->create();

    $this->post('/password/forgot', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether an email exists', function () {
    Notification::fake();

    $this->post('/password/forgot', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->intern()->create();
    $this->post('/password/forgot', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get('/password/reset/'.$notification->token.'?email='.$user->email)->assertOk();

        $this->post('/password/reset', [
            'token' => $notification->token, 'email' => $user->email,
            'password' => 'New-Secret-123', 'password_confirmation' => 'New-Secret-123',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        return true;
    });

    expect(password_verify('New-Secret-123', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid token', function () {
    $user = User::factory()->intern()->create();

    $this->post('/password/reset', [
        'token' => 'bogus', 'email' => $user->email,
        'password' => 'New-Secret-123', 'password_confirmation' => 'New-Secret-123',
    ])->assertSessionHasErrors('email');
});
