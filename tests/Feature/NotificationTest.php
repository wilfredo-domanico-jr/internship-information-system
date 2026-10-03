<?php

use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyRegistered;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->other = User::factory()->admin()->create();
    $company = Company::factory()->registered()->pending()->create(['name' => 'BlueOrbit Analytics']);
    $this->admin->notify(new CompanyRegistered($company));
    $this->other->notify(new CompanyRegistered(Company::factory()->registered()->create(['name' => 'Elsewhere Corp'])));
});

it('lists only my notifications', function () {
    $this->actingAs($this->admin)->get('/notifications')
        ->assertOk()
        ->assertSee('BlueOrbit Analytics')
        ->assertDontSee('Elsewhere Corp');
});

it('shows the unread count in the bell', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('1 unread');
});

it('opens a notification, marks it read and follows its link', function () {
    $notification = $this->admin->notifications()->first();

    $this->actingAs($this->admin)->get("/notifications/{$notification->id}")
        ->assertRedirect(route('admin.dashboard'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all as read', function () {
    $this->actingAs($this->admin)->post('/notifications/read-all')->assertRedirect();

    expect($this->admin->unreadNotifications()->count())->toBe(0)
        ->and($this->other->unreadNotifications()->count())->toBe(1);
});

it('deletes a notification', function () {
    $notification = $this->admin->notifications()->first();

    $this->actingAs($this->admin)->delete("/notifications/{$notification->id}")->assertRedirect();

    expect($this->admin->notifications()->count())->toBe(0);
});

it('returns 404 for another user\'s notification', function () {
    $foreign = $this->other->notifications()->first();

    $this->actingAs($this->admin)->get("/notifications/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->admin)->delete("/notifications/{$foreign->id}")->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});
