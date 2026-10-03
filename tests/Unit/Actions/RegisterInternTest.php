<?php

use App\Actions\RegisterIntern;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Notifications\InternJoinedClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates an intern with a profile in the class and notifies the adviser', function () {
    Notification::fake();
    $section = ClassSection::factory()->create(['join_code' => 'JOIN2026']);

    $user = app(RegisterIntern::class)([
        'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos',
        'email' => 'maria@example.com', 'student_number' => '21-0001',
        'join_code' => 'JOIN2026', 'password' => 'Secret-Pass-123',
    ]);

    expect($user->role)->toBe(Role::Intern)
        ->and($user->member_no)->toStartWith('INT-')
        ->and($user->internProfile->class_section_id)->toBe($section->id)
        ->and($user->internProfile->school_year)->toBe($section->school_year)
        ->and(password_verify('Secret-Pass-123', $user->password))->toBeTrue();

    Notification::assertSentTo($section->adviser, InternJoinedClass::class);
});

it('registers into a class that has no adviser yet without failing', function () {
    Notification::fake();
    ClassSection::factory()->unassigned()->create(['join_code' => 'NOADV123']);

    $user = app(RegisterIntern::class)([
        'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Reyes',
        'email' => 'pedro@example.com', 'student_number' => '21-0002',
        'join_code' => 'NOADV123', 'password' => 'Secret-Pass-123',
    ]);

    expect($user->internProfile->classSection->join_code)->toBe('NOADV123');
    Notification::assertNothingSent();
});
