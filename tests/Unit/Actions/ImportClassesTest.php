<?php

use App\Actions\ImportClasses;
use App\Enums\ClassStatus;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use App\Support\ImportColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function classRow(array $overrides = []): array
{
    return array_merge([
        'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'monday',
        'starts_at' => '8:00 AM', 'ends_at' => '12:00', 'school_year' => '2025-2026', 'adviser_member_no' => null, '_row' => 2,
    ], $overrides);
}

it('creates classes with join codes, normalized times and optional adviser', function () {
    $adviser = User::factory()->adviser()->create(['member_no' => 'ADV-2026-00001']);

    $result = app(ImportClasses::class)(collect([
        classRow(['adviser_member_no' => 'adv-2026-00001']),
        classRow(['course_code' => 'IT401', 'section' => 'SBIT-4A', 'day' => 'Friday', 'starts_at' => '13:00:00', 'ends_at' => '17:00', '_row' => 3]),
    ]));

    expect($result->failed())->toBeFalse()->and($result->created)->toBe(2);
    $cc = ClassSection::where('course_code', 'CC101')->firstOrFail();
    expect($cc->day)->toBe('Monday')
        ->and($cc->starts_at)->toBe('08:00:00')->and($cc->ends_at)->toBe('12:00:00')
        ->and($cc->join_code)->toHaveLength(8)
        ->and($cc->status)->toBe(ClassStatus::Active)
        ->and($cc->adviser_id)->toBe($adviser->id)
        ->and(ClassAdviserLog::where('class_section_id', $cc->id)->where('adviser_id', $adviser->id)->exists())->toBeTrue()
        ->and(ClassSection::where('course_code', 'IT401')->value('adviser_id'))->toBeNull();
});

it('rejects bad days, times, school years, unknown advisers and duplicates', function () {
    ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'school_year' => '2025-2026']);

    $result = app(ImportClasses::class)(collect([
        classRow(['_row' => 2]),                                                     // duplicates an existing active class
        classRow(['section' => 'SBIT-4B', 'day' => 'Funday', '_row' => 3]),
        classRow(['section' => 'SBIT-4D', 'starts_at' => '13:00', 'ends_at' => '09:00', '_row' => 4]),
        classRow(['section' => 'SBIT-4E', 'school_year' => '2025', '_row' => 5]),
        classRow(['section' => 'SBIT-4F', 'adviser_member_no' => 'ADV-0000-00000', '_row' => 6]),
        classRow(['section' => 'SBIT-4G', '_row' => 7]),
        classRow(['section' => 'sbit-4g', '_row' => 8]),                            // duplicates row 7 in the file
    ]));

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->errors))->toBe([2, 3, 4, 5, 6, 8])
        ->and(ClassSection::count())->toBe(1);
});

it('rejects values that are not real clock times', function (string $bad) {
    $result = app(ImportClasses::class)(collect([classRow(['starts_at' => $bad, 'ends_at' => '23:00'])]));

    expect($result->failed())->toBeTrue()
        ->and($result->errors[2][0])->toContain('valid times')
        ->and(ClassSection::count())->toBe(0);
})->with(['24:00', '0.3333', 'tomorrow', '13:00 PM', '8']);

it('accepts the supported time formats and stores H:i:s', function (string $input, string $stored) {
    $result = app(ImportClasses::class)(collect([classRow(['starts_at' => $input, 'ends_at' => '23:00'])]));

    expect($result->failed())->toBeFalse()
        ->and(ClassSection::firstOrFail()->starts_at)->toBe($stored);
})->with([['08:00', '08:00:00'], ['8:00 AM', '08:00:00'], ['13:00:00', '13:00:00'], ['1:05pm', '13:05:00']]);

it('rejects over-long class fields', function () {
    $result = app(ImportClasses::class)(collect([
        classRow(['course_code' => str_repeat('A', 31), 'section' => str_repeat('B', 51), 'subject' => str_repeat('c', 256), 'school_year' => '2025-2026-2027-2028-29']),
    ]));

    expect($result->failed())->toBeTrue()
        ->and(implode(' ', $result->errors[2]))->toContain('course code')->toContain('section')->toContain('subject')->toContain('school year')
        ->and(ClassSection::count())->toBe(0);
});

it('rejects the untouched template example row', function () {
    $row = array_combine(ImportColumns::CLASSES, ImportColumns::EXAMPLES['classes']) + ['_row' => 2];

    $result = app(ImportClasses::class)(collect([$row]));

    expect($result->failed())->toBeTrue()
        ->and(implode(' ', $result->errors[2]))->toContain("template's example row")
        ->and(ClassSection::count())->toBe(0);
});
