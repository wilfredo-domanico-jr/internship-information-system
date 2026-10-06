<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\User;
use App\Notifications\DtrReviewed;
use Illuminate\Support\Facades\DB;

/** Never touches hours. */
class DisapproveDtr
{
    public function __invoke(Dtr $dtr, User $reviewer, string $note): Dtr
    {
        $note = trim($note);

        if ($note === '') {
            throw new DomainRuleViolation('Tell the intern why the DTR was disapproved.');
        }

        DB::transaction(function () use ($dtr, $reviewer, $note) {
            $locked = Dtr::query()->whereKey($dtr->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DtrStatus::Pending) {
                throw new DomainRuleViolation('This DTR has already been reviewed.');
            }

            $locked->update([
                'status' => DtrStatus::Disapproved,
                'reviewer_id' => $reviewer->id,
                'reviewer_note' => $note,
                'reviewed_at' => now(),
            ]);
        });

        $dtr->refresh()->placement->intern->notify(new DtrReviewed($dtr));

        return $dtr;
    }
}
