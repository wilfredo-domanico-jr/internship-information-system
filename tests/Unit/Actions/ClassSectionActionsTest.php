<?php

use App\Actions\CreateClassSection;
use App\Actions\UpdateClassSection;
use App\Enums\ClassStatus;
use App\Models\ClassAdviserLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an active class owned by the adviser with a join code and a log row', function () {
    $adviser = User::factory()->adviser()->create();

    $section = app(CreateClassSection::class)([
        'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'Monday',
        'starts_at' => '08:00', 'ends_at' => '12:00', 'school_year' => '2025-2026',
    ], $adviser);

    expect($section->adviser_id)->toBe($adviser->id)
        ->and($section->status)->toBe(ClassStatus::Active)
        ->and($section->join_code)->toHaveLength(8)
        ->and($section->starts_at)->toBe('08:00:00')
        ->and($section->ends_at)->toBe('12:00:00')
        ->and(ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->whereNull('left_at')->count())->toBe(1);
});

it('updates the schedule without touching the join code or adviser', function () {
    $adviser = User::factory()->adviser()->create();
    $section = app(CreateClassSection::class)([
        'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'Monday',
        'starts_at' => '08:00', 'ends_at' => '12:00', 'school_year' => '2025-2026',
    ], $adviser);
    $code = $section->join_code;

    app(UpdateClassSection::class)($section, [
        'course_code' => 'IT401', 'subject' => 'Internship 1', 'section' => 'SBIT-4D', 'day' => 'Friday',
        'starts_at' => '09:30', 'ends_at' => '11:30', 'school_year' => '2026-2027',
    ]);

    $section->refresh();
    expect($section->course_code)->toBe('IT401')->and($section->day)->toBe('Friday')->and($section->starts_at)->toBe('09:30:00')
        ->and($section->join_code)->toBe($code)->and($section->adviser_id)->toBe($adviser->id);
});
