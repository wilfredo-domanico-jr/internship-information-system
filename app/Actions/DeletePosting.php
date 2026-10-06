<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\InternshipPosting;

/** Postings with applications are history; close them instead of deleting. */
class DeletePosting
{
    public function __invoke(InternshipPosting $posting): void
    {
        if ($posting->applications()->exists()) {
            throw new DomainRuleViolation('This posting already has applicants. Close it instead of deleting it.');
        }

        $posting->delete();
    }
}
