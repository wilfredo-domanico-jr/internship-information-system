<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\User;
use App\Notifications\DtrReviewed;

/** Never touches hours. */
class DisapproveDtr
{
    public function __invoke(Dtr $dtr, User $reviewer, string $note): Dtr
    {
        if ($dtr->status !== DtrStatus::Pending) {
            throw new DomainRuleViolation('This DTR has already been reviewed.');
        }

        $note = trim($note);

        if ($note === '') {
            throw new DomainRuleViolation('Tell the intern why the DTR was disapproved.');
        }

        $dtr->update([
            'status' => DtrStatus::Disapproved,
            'reviewer_id' => $reviewer->id,
            'reviewer_note' => $note,
            'reviewed_at' => now(),
        ]);

        $dtr->placement->intern->notify(new DtrReviewed($dtr));

        return $dtr;
    }
}
