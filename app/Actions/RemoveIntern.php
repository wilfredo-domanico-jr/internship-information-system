<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\Placement;
use App\Notifications\InternRemoved;

/** The company ends the placement; approved hours stay on the record and in the intern's total. */
class RemoveIntern
{
    public function __invoke(Placement $placement): Placement
    {
        if (! $placement->isActive()) {
            throw new DomainRuleViolation('This placement has already ended.');
        }

        $placement->update(['ended_at' => today()]);

        $placement->intern->notify(new InternRemoved($placement->load('company')));

        return $placement;
    }
}
