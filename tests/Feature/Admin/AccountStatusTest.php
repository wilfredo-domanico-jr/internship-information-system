<?php

use App\Enums\AccountStatus;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('disables an intern, who is then signed out, and shows them in the archive', function () {
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->actingAs($intern)->get(route('intern.dashboard'))->assertOk();

    $this->actingAs($this->admin)->from(route('admin.interns.show', $intern))
        ->post(route('admin.users.disable', $intern))
        ->assertRedirect(route('admin.interns.show', $intern))->assertSessionHas('success');

    expect($intern->refresh()->status)->toBe(AccountStatus::Disabled);
    $this->actingAs($intern)->get(route('intern.dashboard'))->assertRedirect(route('login'));
    $this->actingAs($this->admin)->get(route('admin.archive.index'))->assertOk()->assertSee('Maria Santos');
});

it('reactivates from the archive', function () {
    $adviser = User::factory()->adviser()->disabled()->create(['first_name' => 'Elsie']);

    $this->actingAs($this->admin)->get(route('admin.archive.index', ['role' => 'adviser']))->assertSee('Elsie');
    $this->actingAs($this->admin)->get(route('admin.archive.index', ['role' => 'intern']))->assertDontSee('Elsie');

    $this->actingAs($this->admin)->post(route('admin.users.reactivate', $adviser))->assertRedirect()->assertSessionHas('success');
    expect($adviser->refresh()->status)->toBe(AccountStatus::Active);
    $this->actingAs($this->admin)->get(route('admin.archive.index'))->assertSee('No disabled accounts');
});

it('refuses to disable an admin or yourself with a flash error', function () {
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin)->post(route('admin.users.disable', $other))->assertRedirect()->assertSessionHas('error');
    $this->actingAs($this->admin)->post(route('admin.users.disable', $this->admin))->assertRedirect()->assertSessionHas('error');
    expect($other->refresh()->status)->toBe(AccountStatus::Active)->and($this->admin->refresh()->status)->toBe(AccountStatus::Active);
});

it('shows the danger zone on intern, adviser and company pages', function () {
    $intern = User::factory()->intern()->create();
    $adviser = User::factory()->adviser()->create();
    $companyUser = User::factory()->company()->create();

    $this->actingAs($this->admin)->get(route('admin.interns.show', $intern))->assertSee(route('admin.users.disable', $intern));
    $this->actingAs($this->admin)->get(route('admin.advisers.show', $adviser))->assertSee(route('admin.users.disable', $adviser));
    $this->actingAs($this->admin)->get(route('admin.companies.show', $companyUser->company))->assertSee(route('admin.users.disable', $companyUser));
});

it('is admin-only', function () {
    $intern = User::factory()->intern()->create();
    $this->actingAs($intern)->post(route('admin.users.disable', $intern))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.archive.index'))->assertForbidden();
});
