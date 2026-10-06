<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassSubmissionReviewed;
use InvalidArgumentException;

/** Approve or decline. A decision may be changed later; the intern is told each time. */
class ReviewClassSubmission
{
    public function __invoke(ClassSubmission $submission, User $reviewer, SubmissionStatus $decision, ?string $note = null): ClassSubmission
    {
        if ($decision === SubmissionStatus::Pending) {
            throw new InvalidArgumentException('A review must approve or decline the submission.');
        }

        $note = trim((string) $note) ?: null;

        if ($decision === SubmissionStatus::Declined && $note === null) {
            throw new DomainRuleViolation('Tell the intern why the document was declined.');
        }

        $submission->update([
            'status' => $decision,
            'reviewer_note' => $note,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $submission->intern->notify(new ClassSubmissionReviewed($submission));

        return $submission;
    }
}
