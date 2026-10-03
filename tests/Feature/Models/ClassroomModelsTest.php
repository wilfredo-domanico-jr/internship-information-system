<?php

use App\Enums\ClassStatus;
use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\InternProfile;
use App\Models\User;

it('links a class to its adviser and interns', function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->for($adviser, 'adviser')->create();
    InternProfile::factory()->count(3)->for($section)->create();

    expect($section->adviser->is($adviser))->toBeTrue()
        ->and($section->internProfiles)->toHaveCount(3)
        ->and($section->interns)->toHaveCount(3)
        ->and($adviser->advisedClasses)->toHaveCount(1)
        ->and($section->status)->toBe(ClassStatus::Active)
        ->and($section->join_code)->toHaveLength(8);
});

it('formats the schedule label', function () {
    $section = ClassSection::factory()->make(['day' => 'Monday', 'starts_at' => '08:00:00', 'ends_at' => '12:00:00']);

    expect($section->schedule_label)->toBe('Monday 8:00 AM – 12:00 PM');
});

it('tracks folder submissions with status and late flag', function () {
    $folder = ClassFolder::factory()->create(['is_locked' => true]);
    $submission = ClassSubmission::factory()->for($folder, 'folder')->create(['is_late' => true]);

    expect($submission->status)->toBe(SubmissionStatus::Pending)
        ->and($submission->is_late)->toBeTrue()
        ->and($folder->submissions)->toHaveCount(1)
        ->and($submission->intern->isIntern())->toBeTrue()
        ->and($folder->classSection)->not->toBeNull();
});

it('threads comments under announcements', function () {
    $announcement = Announcement::factory()->hasComments(2)->create();

    expect($announcement->comments)->toHaveCount(2)
        ->and($announcement->author)->not->toBeNull()
        ->and($announcement->classSection->announcements)->toHaveCount(1);
});

it('scopes active classes', function () {
    ClassSection::factory()->create();
    ClassSection::factory()->archived()->create();

    expect(ClassSection::active()->count())->toBe(1);
});
