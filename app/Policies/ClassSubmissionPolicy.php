<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\ClassSubmission;
use App\Models\User;

class ClassSubmissionPolicy
{
    public function view(User $user, ClassSubmission $submission): bool
    {
        return $user->isAdmin()
            || $submission->intern_id === $user->id
            || $submission->folder->classSection->isAdvisedBy($user);
    }

    public function review(User $user, ClassSubmission $submission): bool
    {
        return $submission->folder->classSection->isAdvisedBy($user);
    }

    /** Interns may withdraw a submission only before it is reviewed. */
    public function delete(User $user, ClassSubmission $submission): bool
    {
        return $submission->intern_id === $user->id && $submission->status === SubmissionStatus::Pending;
    }
}
