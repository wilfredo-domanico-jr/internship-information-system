<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;

class CancelApplication
{
    public function __invoke(Application $application): Application
    {
        if (! $application->status->isOpen()) {
            throw new DomainRuleViolation('Only pending or for-interview applications can be cancelled.');
        }

        $application->update(['status' => ApplicationStatus::Cancelled, 'decided_at' => now()]);

        return $application;
    }
}
