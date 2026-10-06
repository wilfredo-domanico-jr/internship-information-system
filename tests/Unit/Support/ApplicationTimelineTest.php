<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Interview;
use App\Support\ApplicationTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function timelineStates(Application $application): array
{
    return collect(ApplicationTimeline::steps($application->load('interview')))->pluck('state', 'label')->all();
}

it('walks pending → interview → decision', function () {
    $pending = Application::factory()->create();
    expect(timelineStates($pending))->toBe(['Applied' => 'done', 'Interview' => 'upcoming', 'Decision' => 'upcoming']);

    $interview = Interview::factory()->create(['venue' => 'Google Meet']);
    $steps = ApplicationTimeline::steps($interview->application->load('interview'));
    expect($steps[1]['state'])->toBe('current')->and($steps[1]['description'])->toContain('Google Meet');

    $accepted = Application::factory()->accepted()->create(['decided_at' => now()]);
    expect(timelineStates($accepted))->toBe(['Applied' => 'done', 'Interview' => 'done', 'Decision' => 'done']);
});

it('marks declined and cancelled applications as failed with the reason', function () {
    $declined = Application::factory()->declined()->create(['decline_reason' => 'Position filled', 'decided_at' => now()]);
    $steps = ApplicationTimeline::steps($declined->load('interview'));
    expect($steps[2]['state'])->toBe('failed')->and($steps[2]['label'])->toBe('Declined')->and($steps[2]['description'])->toBe('Position filled');

    $cancelled = Application::factory()->create(['status' => ApplicationStatus::Cancelled, 'decided_at' => now()]);
    $steps = ApplicationTimeline::steps($cancelled->load('interview'));
    expect($steps[2]['state'])->toBe('failed')->and($steps[2]['label'])->toBe('Cancelled');
});
