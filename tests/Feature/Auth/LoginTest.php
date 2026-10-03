<?php

use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

it('signs in with valid credentials and records the login time', function () {
    $user = User::factory()->intern()->create(['email' => 'juan@example.com']);

    $this->post('/login', ['email' => 'juan@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('normalizes the email before authenticating', function () {
    $user = User::factory()->intern()->create(['email' => 'juan@example.com']);

    $this->post('/login', ['email' => '  Juan@Example.COM ', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->intern()->create(['email' => 'juan@example.com']);

    $this->from('/login')->post('/login', ['email' => 'juan@example.com', 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses disabled accounts with a support hint', function () {
    User::factory()->intern()->disabled()->create(['email' => 'off@example.com']);

    $this->post('/login', ['email' => 'off@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain(config('wiis.support.email'));

    $this->assertGuest();
});

it('sends pending companies to the verification page after login', function () {
    $user = User::factory()->company()->create(['email' => 'co@example.com']);
    $user->company->update(['approval_status' => ApprovalStatus::Pending]);

    $this->post('/login', ['email' => 'co@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('account.pending'));
});

it('throttles after five failed attempts', function () {
    RateLimiter::clear('juan@example.com|127.0.0.1');
    User::factory()->intern()->create(['email' => 'juan@example.com']);

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'juan@example.com', 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => 'juan@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Too many');

    $this->assertGuest();
});

it('signs out', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});
