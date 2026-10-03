<?php

use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/admin/dashboard')->assertRedirect('/login');
});

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('sends each role to its own dashboard', function (string $state, string $route) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route($route));
    $this->actingAs($user)->get(route($route))->assertOk()->assertSee($user->first_name);
    $this->actingAs($user)->get('/login')->assertRedirect(route('dashboard'));
})->with([
    ['admin', 'admin.dashboard'],
    ['adviser', 'adviser.dashboard'],
    ['company', 'company.dashboard'],
    ['intern', 'intern.dashboard'],
]);

it('forbids access to other portals', function () {
    $intern = User::factory()->intern()->create();

    $this->actingAs($intern)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($intern)->get(route('adviser.dashboard'))->assertForbidden();
    $this->actingAs($intern)->get(route('company.dashboard'))->assertForbidden();
});

it('holds unapproved companies on the pending page', function () {
    $user = User::factory()->company()->create();
    $user->company->update(['approval_status' => ApprovalStatus::Pending, 'approved_at' => null]);

    $this->actingAs($user)->get(route('company.dashboard'))->assertRedirect(route('account.pending'));
    $this->actingAs($user)->get(route('account.pending'))->assertOk()->assertSee('pending');
});

it('sends approved companies away from the pending page', function () {
    $user = User::factory()->company()->create();

    $this->actingAs($user)->get(route('account.pending'))->assertRedirect(route('dashboard'));
});

it('logs out a user who was disabled after signing in', function () {
    $user = User::factory()->intern()->create();
    $this->actingAs($user)->get(route('intern.dashboard'))->assertOk();

    $user->update(['status' => AccountStatus::Disabled]);

    $this->get(route('intern.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
