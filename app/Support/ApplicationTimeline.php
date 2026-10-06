<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Support\Carbon;

/** Builds the steps for <x-timeline> from an application (with `interview` loaded). */
class ApplicationTimeline
{
    /** @return array<int, array{label:string, description?:string, at?:Carbon, state:string}> */
    public static function steps(Application $application): array
    {
        $interview = $application->interview;
        $status = $application->status;

        $interviewStep = match (true) {
            $interview !== null => [
                'label' => 'Interview',
                'description' => "{$interview->title} · {$interview->venue} · {$interview->scheduled_on->format('M j, Y')} ".
                    Carbon::parse($interview->starts_at)->format('g:i A'),
                'state' => $status === ApplicationStatus::ForInterview ? 'current' : 'done',
            ],
            $status === ApplicationStatus::Pending => ['label' => 'Interview', 'description' => 'Waiting for the company to review your application.', 'state' => 'upcoming'],
            $status === ApplicationStatus::Accepted => ['label' => 'Interview', 'description' => 'Accepted without an interview.', 'state' => 'done'],
            default => ['label' => 'Interview', 'description' => 'No interview was scheduled.', 'state' => 'upcoming'],
        };

        $decisionStep = match ($status) {
            ApplicationStatus::Accepted => ['label' => 'Decision', 'description' => 'Accepted. Join the company with its company code on My internship.', 'at' => $application->decided_at, 'state' => 'done'],
            ApplicationStatus::Declined => ['label' => 'Declined', 'description' => $application->decline_reason ?? 'The company declined your application.', 'at' => $application->decided_at, 'state' => 'failed'],
            ApplicationStatus::Cancelled => ['label' => 'Cancelled', 'description' => 'You withdrew this application.', 'at' => $application->decided_at, 'state' => 'failed'],
            default => ['label' => 'Decision', 'state' => 'upcoming'],
        };

        return [
            ['label' => 'Applied', 'at' => $application->created_at, 'state' => 'done'],
            $interviewStep,
            $decisionStep,
        ];
    }
}
