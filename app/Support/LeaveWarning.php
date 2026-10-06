<?php

namespace App\Support;

use App\Enums\HoursTier;
use App\Services\OjtHoursService;

/** The warning an intern sees before leaving a company, based on hours rendered there. */
class LeaveWarning
{
    public static function message(int $hoursHere, OjtHoursService $hours): string
    {
        return match ($hours->tier($hoursHere)) {
            HoursTier::Low => "You have rendered {$hoursHere} of the {$hours->certificateMinimum()} hours this company needs before it can issue you a certificate. If you leave now, those hours still count toward your OJT total, but you will not get a certificate from this company.",
            HoursTier::Mid => "You have rendered {$hoursHere} hours here, so you are eligible for a certificate from this company. Ask for it before you leave.",
            HoursTier::Complete => 'You have completed your required hours here. Make sure your certificate has been issued before you leave.',
        };
    }
}
