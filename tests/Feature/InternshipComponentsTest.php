<?php

use Illuminate\Support\Facades\Blade;

it('renders a timeline with done, current, upcoming and failed steps', function () {
    $html = Blade::render('<x-timeline :steps="$steps" />', ['steps' => [
        ['label' => 'Applied', 'at' => now()->subDays(3), 'state' => 'done'],
        ['label' => 'Interview', 'description' => 'Google Meet · Oct 10', 'state' => 'current'],
        ['label' => 'Decision', 'state' => 'upcoming'],
        ['label' => 'Declined', 'description' => 'Position filled', 'state' => 'failed'],
    ]]);

    expect($html)->toContain('Applied')->toContain('Interview')->toContain('Google Meet · Oct 10')->toContain('Position filled')
        ->toContain('data-state="done"')->toContain('data-state="current"')->toContain('data-state="upcoming"')->toContain('data-state="failed"')
        ->toContain(now()->subDays(3)->format('M j'));
});
