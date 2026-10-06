<?php

use App\Services\OjtHoursService;
use App\Support\LeaveWarning;

it('warns according to the hours rendered at this company', function () {
    $hours = app(OjtHoursService::class);

    expect(LeaveWarning::message(100, $hours))->toContain((string) $hours->certificateMinimum())->toContain('certificate')
        ->and(LeaveWarning::message($hours->certificateMinimum(), $hours))->toContain('eligible')
        ->and(LeaveWarning::message($hours->required(), $hours))->toContain('completed');
});
