<?php

use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists advisers with their class count', function () {
    $adviser = User::factory()->adviser()->create(['first_name' => 'Elsie', 'last_name' => 'Isip']);
    ClassSection::factory()->count(2)->for($adviser, 'adviser')->create();
    User::factory()->intern()->create(['first_name' => 'NotAnAdviser']);

    $this->actingAs($this->admin)->get(route('admin.advisers.index'))
        ->assertOk()->assertSee('Elsie Isip')->assertSee('2')->assertDontSee('NotAnAdviser');
    $this->actingAs($this->admin)->get(route('admin.advisers.index', ['q' => 'isip']))->assertSee('Elsie Isip');
    $this->actingAs($this->admin)->get(route('admin.advisers.index', ['q' => 'zzz']))->assertDontSee('Elsie Isip');
});

it('creates an adviser from the form and emails credentials', function () {
    Notification::fake();

    $this->actingAs($this->admin)->get(route('admin.advisers.create'))->assertOk()->assertSee('Add adviser');

    $response = $this->actingAs($this->admin)->post(route('admin.advisers.store'), [
        'first_name' => 'Elsie', 'last_name' => 'Isip', 'email' => ' Elsie@Example.com ', 'phone' => '09170000000',
    ]);

    $adviser = User::where('email', 'elsie@example.com')->firstOrFail();
    $response->assertRedirect(route('admin.advisers.show', $adviser))->assertSessionHas('success');
    Notification::assertSentTo($adviser, AccountCredentials::class);
});

it('rejects duplicate emails', function () {
    User::factory()->intern()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->admin)->post(route('admin.advisers.store'), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

it('shows an adviser with classes and their interns', function () {
    $adviser = User::factory()->adviser()->create(['first_name' => 'Elsie']);
    $section = ClassSection::factory()->for($adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['class_section_id' => $section->id]);

    $this->actingAs($this->admin)->get(route('admin.advisers.show', $adviser))
        ->assertOk()->assertSee('Elsie')->assertSee('CC101')->assertSee('Maria Santos');
    $this->actingAs($this->admin)->get(route('admin.advisers.show', $intern))->assertNotFound();
});
