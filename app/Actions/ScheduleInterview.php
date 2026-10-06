<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\Interview;
use App\Notifications\InterviewScheduled;
use Illuminate\Support\Facades\DB;

/** Moves a pending application to "for interview"; re-running reschedules the single interview. */
class ScheduleInterview
{
    /** @param  array{title:string, venue:string, link?:?string, scheduled_on:string, starts_at:string, ends_at:string, notes?:?string}  $data */
    public function __invoke(Application $application, array $data): Interview
    {
        if (! $application->status->isOpen()) {
            throw new DomainRuleViolation('This application has already been decided.');
        }

        $interview = DB::transaction(function () use ($application, $data) {
            $interview = $application->interview()->updateOrCreate([], [
                'title' => $data['title'],
                'venue' => $data['venue'],
                'link' => $data['link'] ?? null,
                'scheduled_on' => $data['scheduled_on'],
                'starts_at' => self::dbTime($data['starts_at']),
                'ends_at' => self::dbTime($data['ends_at']),
                'notes' => $data['notes'] ?? null,
            ]);

            $application->update(['status' => ApplicationStatus::ForInterview]);

            return $interview;
        });

        $application->intern->notify(new InterviewScheduled($interview->load('application.posting.company')));

        return $interview;
    }

    private static function dbTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }
}
