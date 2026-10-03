<?php

use App\Actions\ImportInterns;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function internRow(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos', 'email' => 'maria@example.com',
        'phone' => '09171234567', 'student_number' => '21-0001', 'section' => 'SBIT-4C', 'school_year' => null, '_row' => 2,
    ], $overrides);
}

beforeEach(function () {
    Notification::fake();
    $this->section = ClassSection::factory()->create(['section' => 'SBIT-4C', 'school_year' => '2025-2026']);
});

it('creates interns in their class and emails credentials', function () {
    $result = app(ImportInterns::class)(collect([
        internRow(),
        internRow(['first_name' => 'Pedro', 'email' => 'PEDRO@Example.com', 'student_number' => '21-0002', 'section' => 'sbit-4c', 'school_year' => '2024-2025', '_row' => 3]),
    ]));

    expect($result->failed())->toBeFalse()->and($result->created)->toBe(2);
    $maria = User::where('email', 'maria@example.com')->firstOrFail();
    $pedro = User::where('email', 'pedro@example.com')->firstOrFail();
    expect($maria->role)->toBe(Role::Intern)
        ->and($maria->member_no)->toStartWith('INT-')
        ->and($maria->internProfile->class_section_id)->toBe($this->section->id)
        ->and($maria->internProfile->school_year)->toBe('2025-2026')
        ->and($pedro->internProfile->school_year)->toBe('2024-2025')
        ->and($pedro->internProfile->student_number)->toBe('21-0002');
    Notification::assertSentTo([$maria, $pedro], AccountCredentials::class);
});

it('fails the whole import when two rows share an email or student number', function () {
    $result = app(ImportInterns::class)(collect([
        internRow(['_row' => 2]),
        internRow(['email' => 'Maria@Example.com', 'student_number' => '21-0009', '_row' => 3]),
        internRow(['email' => 'other@example.com', 'student_number' => '21-0001', '_row' => 4]),
    ]));

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->errors))->toContain(3, 4)
        ->and(User::ofRole(Role::Intern)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('reports unknown sections, taken emails and missing fields by row', function () {
    User::factory()->intern()->create(['email' => 'taken@example.com']);

    $result = app(ImportInterns::class)(collect([
        internRow(['section' => 'NOPE-1A', '_row' => 2]),
        internRow(['email' => 'taken@example.com', 'student_number' => '21-0003', '_row' => 3]),
        internRow(['last_name' => null, 'email' => 'x@example.com', 'student_number' => '21-0004', '_row' => 4]),
    ]));

    expect($result->failed())->toBeTrue()
        ->and(implode(' ', $result->errors[2]))->toContain('section')
        ->and(implode(' ', $result->errors[3]))->toContain('email')
        ->and(implode(' ', $result->errors[4]))->toContain('last name')
        ->and(User::ofRole(Role::Intern)->count())->toBe(1);
});

it('rejects a section name that matches more than one active class', function () {
    ClassSection::factory()->create(['section' => 'SBIT-4C', 'school_year' => '2024-2025']);

    $result = app(ImportInterns::class)(collect([internRow()]));

    expect($result->failed())->toBeTrue()->and(implode(' ', $result->errors[2]))->toContain('more than one');
});
