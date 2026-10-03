<?php

use App\Enums\ClassStatus;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(function () {
    $this->section = ClassSection::factory()->create(['join_code' => 'JOIN2026']);
    $this->payload = [
        'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com',
        'student_number' => '21-0001', 'join_code' => 'JOIN2026',
        'password' => 'Secret-Pass-123', 'password_confirmation' => 'Secret-Pass-123', 'terms' => '1',
    ];
});

it('shows the registration form', function () {
    $this->get('/register/intern')->assertOk()->assertSee('join code', false);
});

it('registers, signs in and lands on the intern dashboard', function () {
    $this->post('/register/intern', $this->payload)->assertRedirect(route('intern.dashboard'));

    $this->assertAuthenticated();
    expect(User::where('email', 'maria@example.com')->first()->internProfile->class_section_id)->toBe($this->section->id);
});

it('normalizes email and join code casing', function () {
    $this->post('/register/intern', [...$this->payload, 'email' => ' Maria@Example.com ', 'join_code' => ' join2026 '])
        ->assertRedirect(route('intern.dashboard'));

    expect(User::where('email', 'maria@example.com')->exists())->toBeTrue();
});

it('rejects an unknown join code', function () {
    $this->post('/register/intern', [...$this->payload, 'join_code' => 'NOPE1234'])
        ->assertSessionHasErrors('join_code');

    $this->assertGuest();
    expect(User::count())->toBe(1); // only the adviser from the factory
});

it('rejects the join code of an archived class', function () {
    $this->section->update(['status' => ClassStatus::Archived]);

    $this->post('/register/intern', $this->payload)->assertSessionHasErrors('join_code');
    $this->assertGuest();
});

it('rejects duplicate email and student number', function () {
    $existing = User::factory()->intern()->create(['email' => 'maria@example.com']);

    $this->post('/register/intern', $this->payload)->assertSessionHasErrors('email');

    $this->post('/register/intern', [...$this->payload, 'email' => 'other@example.com', 'student_number' => $existing->internProfile->student_number])
        ->assertSessionHasErrors('student_number');
});

it('requires accepting the terms and a confirmed password', function () {
    $this->post('/register/intern', [...$this->payload, 'terms' => null])->assertSessionHasErrors('terms');
    $this->post('/register/intern', [...$this->payload, 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
});
