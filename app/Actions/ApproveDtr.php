<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\InternProfile;
use App\Models\User;
use App\Notifications\DtrReviewed;
use Illuminate\Support\Facades\DB;

/**
 * The hours ledger's only writer. Runs in a transaction and credits hours exactly once,
 * on the pending → approved transition. Disapproved DTRs can never be approved later.
 */
class ApproveDtr
{
    public function __invoke(Dtr $dtr, User $reviewer): Dtr
    {
        DB::transaction(function () use ($dtr, $reviewer) {
            $locked = Dtr::query()->whereKey($dtr->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DtrStatus::Pending) {
                throw new DomainRuleViolation('This DTR has already been reviewed.');
            }

            $locked->update([
                'status' => DtrStatus::Approved,
                'reviewer_id' => $reviewer->id,
                'reviewer_note' => null,
                'reviewed_at' => now(),
            ]);

            $placement = $locked->placement;
            $placement->increment('hours_rendered', $locked->hours);
            $placement->increment('absences', $locked->absences);

            InternProfile::query()->where('user_id', $placement->intern_id)->increment('total_hours', $locked->hours);
            InternProfile::query()->where('user_id', $placement->intern_id)->increment('total_absences', $locked->absences);
        });

        $dtr->refresh()->placement->intern->notify(new DtrReviewed($dtr));

        return $dtr;
    }
}
