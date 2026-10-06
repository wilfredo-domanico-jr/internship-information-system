<?php

use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
    $this->otherAdviser = User::factory()->adviser()->create();
    $this->otherIntern = User::factory()->intern()->create();
    $this->admin = User::factory()->admin()->create();
});

it('knows its members', function () {
    expect($this->section->isAdvisedBy($this->adviser))->toBeTrue()
        ->and($this->section->isAdvisedBy($this->otherAdviser))->toBeFalse()
        ->and($this->section->enrolls($this->intern))->toBeTrue()
        ->and($this->section->enrolls($this->otherIntern))->toBeFalse()
        ->and($this->section->enrolls($this->adviser))->toBeFalse()
        ->and($this->section->hasMember($this->intern))->toBeTrue()
        ->and($this->section->hasMember($this->adviser))->toBeTrue()
        ->and($this->section->hasMember($this->admin))->toBeFalse();
});

it('lets members and admins view a class but only its adviser manage it', function () {
    expect($this->adviser->can('view', $this->section))->toBeTrue()
        ->and($this->intern->can('view', $this->section))->toBeTrue()
        ->and($this->admin->can('view', $this->section))->toBeTrue()
        ->and($this->otherAdviser->can('view', $this->section))->toBeFalse()
        ->and($this->otherIntern->can('view', $this->section))->toBeFalse()
        ->and($this->adviser->can('manage', $this->section))->toBeTrue()
        ->and($this->admin->can('manage', $this->section))->toBeFalse()
        ->and($this->intern->can('manage', $this->section))->toBeFalse();
});

it('limits announcement edits to the author while they still advise the class', function () {
    $announcement = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create();

    expect($this->adviser->can('update', $announcement))->toBeTrue()
        ->and($this->adviser->can('delete', $announcement))->toBeTrue()
        ->and($this->intern->can('update', $announcement))->toBeFalse()
        ->and($this->intern->can('view', $announcement))->toBeTrue()
        ->and($this->intern->can('comment', $announcement))->toBeTrue()
        ->and($this->adviser->can('comment', $announcement))->toBeTrue()
        ->and($this->otherIntern->can('comment', $announcement))->toBeFalse()
        ->and($this->admin->can('comment', $announcement))->toBeFalse();

    $this->section->update(['adviser_id' => $this->otherAdviser->id]);

    expect($this->adviser->can('update', $announcement->fresh()))->toBeFalse()
        ->and($this->otherAdviser->can('update', $announcement->fresh()))->toBeFalse();
});

it('lets comment authors and the class adviser delete comments', function () {
    $announcement = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create();
    $comment = AnnouncementComment::factory()->for($announcement)->for($this->intern, 'author')->create();

    expect($this->intern->can('delete', $comment))->toBeTrue()
        ->and($this->adviser->can('delete', $comment))->toBeTrue()
        ->and($this->otherIntern->can('delete', $comment))->toBeFalse()
        ->and($this->otherAdviser->can('delete', $comment))->toBeFalse();
});

it('guards folders, submissions and resources by class membership', function () {
    $folder = ClassFolder::factory()->for($this->section)->create();
    $submission = ClassSubmission::factory()->for($folder, 'folder')->for($this->intern, 'intern')->create();
    $resource = ClassResource::factory()->for($this->section)->for($this->adviser, 'uploader')->create();

    expect($this->intern->can('view', $folder))->toBeTrue()
        ->and($this->intern->can('submit', $folder))->toBeTrue()
        ->and($this->adviser->can('submit', $folder))->toBeFalse()
        ->and($this->adviser->can('manage', $folder))->toBeTrue()
        ->and($this->intern->can('manage', $folder))->toBeFalse()
        ->and($this->otherIntern->can('view', $folder))->toBeFalse()
        ->and($this->intern->can('view', $submission))->toBeTrue()
        ->and($this->adviser->can('view', $submission))->toBeTrue()
        ->and($this->admin->can('view', $submission))->toBeTrue()
        ->and($this->otherIntern->can('view', $submission))->toBeFalse()
        ->and($this->adviser->can('review', $submission))->toBeTrue()
        ->and($this->intern->can('review', $submission))->toBeFalse()
        ->and($this->intern->can('delete', $submission))->toBeTrue()
        ->and($this->adviser->can('delete', $submission))->toBeFalse()
        ->and($this->intern->can('view', $resource))->toBeTrue()
        ->and($this->admin->can('view', $resource))->toBeTrue()
        ->and($this->otherIntern->can('view', $resource))->toBeFalse()
        ->and($this->adviser->can('delete', $resource))->toBeTrue()
        ->and($this->intern->can('delete', $resource))->toBeFalse();

    $submission->update(['status' => SubmissionStatus::Approved]);

    expect($this->intern->can('delete', $submission->fresh()))->toBeFalse();
});
