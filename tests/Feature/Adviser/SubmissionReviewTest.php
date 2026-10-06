<?php

use App\Enums\SubmissionStatus;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassSubmissionReviewed;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->folder = ClassFolder::factory()->for($this->section)->create();
    $this->submission = ClassSubmission::factory()->for($this->folder, 'folder')->create(['title' => 'Endorsement']);
});

it('approves from the folder page and notifies the intern', function () {
    Notification::fake();

    $this->actingAs($this->adviser)->get(route('adviser.folders.show', $this->folder))
        ->assertSee(route('adviser.submissions.approve', $this->submission))->assertSee(route('adviser.submissions.decline', $this->submission));

    $this->actingAs($this->adviser)->from(route('adviser.folders.show', $this->folder))
        ->post(route('adviser.submissions.approve', $this->submission))
        ->assertRedirect(route('adviser.folders.show', $this->folder))->assertSessionHas('success');

    expect($this->submission->refresh()->status)->toBe(SubmissionStatus::Approved)->and($this->submission->reviewed_by)->toBe($this->adviser->id);
    Notification::assertSentTo($this->submission->intern, ClassSubmissionReviewed::class);
});

it('declines with a required note', function () {
    $this->actingAs($this->adviser)->post(route('adviser.submissions.decline', $this->submission), ['note' => ''])->assertSessionHasErrors('note');
    expect($this->submission->refresh()->status)->toBe(SubmissionStatus::Pending);

    $this->actingAs($this->adviser)->post(route('adviser.submissions.decline', $this->submission), ['note' => 'Please use the official template.'])
        ->assertRedirect()->assertSessionHas('success');
    expect($this->submission->refresh()->status)->toBe(SubmissionStatus::Declined)->and($this->submission->reviewer_note)->toBe('Please use the official template.');

    $this->actingAs($this->adviser)->get(route('adviser.folders.show', [$this->folder, 'status' => 'declined']))->assertSee('Please use the official template.');
});

it('forbids advisers of other classes', function () {
    $other = User::factory()->adviser()->create();

    $this->actingAs($other)->post(route('adviser.submissions.approve', $this->submission))->assertForbidden();
    $this->actingAs($other)->post(route('adviser.submissions.decline', $this->submission), ['note' => 'x'])->assertForbidden();
    expect($this->submission->refresh()->status)->toBe(SubmissionStatus::Pending);
});
