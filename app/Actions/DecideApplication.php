<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Notifications\ApplicationDecided;
use InvalidArgumentException;

/** Accept or decline. Accepting never places the intern; they join with the company code. */
class DecideApplication
{
    public function __invoke(Application $application, ApplicationStatus $decision, ?string $reason = null): Application
    {
        if (! in_array($decision, [ApplicationStatus::Accepted, ApplicationStatus::Declined], true)) {
            throw new InvalidArgumentException('A decision must accept or decline the application.');
        }

        if (! $application->status->isOpen()) {
            throw new DomainRuleViolation('This application has already been decided.');
        }

        if ($decision === ApplicationStatus::Accepted && $application->intern->hasActivePlacement()) {
            throw new DomainRuleViolation('This applicant is already placed with a company.');
        }

        $reason = trim((string) $reason) ?: null;

        if ($decision === ApplicationStatus::Declined && $reason === null) {
            throw new DomainRuleViolation('Tell the applicant why the application was declined.');
        }

        $application->update([
            'status' => $decision,
            'decline_reason' => $decision === ApplicationStatus::Declined ? $reason : null,
            'decided_at' => now(),
        ]);

        $application->intern->notify(new ApplicationDecided($application->load('posting.company')));

        return $application;
    }
}
