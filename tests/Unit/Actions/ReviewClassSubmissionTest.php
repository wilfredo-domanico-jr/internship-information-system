<?php

use App\Actions\ReviewClassSubmission;
use App\Enums\SubmissionStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassSubmission;
use App\Notifications\ClassSubmissionReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('approves a submission, records the reviewer and notifies the intern', function () {
    Notification::fake();
    $submission = ClassSubmission::factory()->create();
    $adviser = $submission->folder->classSection->adviser;

    app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Approved);

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::Approved)->and($submission->reviewed_by)->toBe($adviser->id)
        ->and($submission->reviewed_at)->not->toBeNull()->and($submission->reviewer_note)->toBeNull();
    Notification::assertSentTo($submission->intern, ClassSubmissionReviewed::class, function (ClassSubmissionReviewed $n) use ($submission) {
        $data = $n->toArray($submission->intern);

        return str_contains($data['title'], 'approved') && $data['url'] === route('intern.folders.show', $submission->folder);
    });
});

it('declining requires a note and stores it', function () {
    Notification::fake();
    $submission = ClassSubmission::factory()->create();
    $adviser = $submission->folder->classSection->adviser;

    expect(fn () => app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Declined, '  '))->toThrow(DomainRuleViolation::class);
    expect($submission->refresh()->status)->toBe(SubmissionStatus::Pending);

    app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Declined, ' Wrong template. ');
    expect($submission->refresh()->status)->toBe(SubmissionStatus::Declined)->and($submission->reviewer_note)->toBe('Wrong template.');
    Notification::assertSentTo($submission->intern, ClassSubmissionReviewed::class, fn (ClassSubmissionReviewed $n) => str_contains($n->toArray($submission->intern)['body'], 'Wrong template.'));
});

it('allows a decision to be changed and refuses pending as a decision', function () {
    Notification::fake();
    $submission = ClassSubmission::factory()->declined()->create(['reviewer_note' => 'Old note']);
    $adviser = $submission->folder->classSection->adviser;

    app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Approved);
    expect($submission->refresh()->status)->toBe(SubmissionStatus::Approved)->and($submission->reviewer_note)->toBeNull();

    expect(fn () => app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Pending))->toThrow(InvalidArgumentException::class);
});
