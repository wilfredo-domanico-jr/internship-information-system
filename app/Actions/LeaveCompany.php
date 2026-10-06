<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternLeftCompany;

/** Ends the active placement; hours already approved stay on the record and in the intern's total. */
class LeaveCompany
{
    public function __invoke(User $intern): Placement
    {
        $placement = $intern->activePlacement()->with('company.user')->first();

        if (! $placement) {
            throw new DomainRuleViolation('You are not placed with a company right now.');
        }

        $placement->update(['ended_at' => today()]);

        $placement->company->user?->notify(new InternLeftCompany($placement->load('intern')));

        return $placement;
    }
}
