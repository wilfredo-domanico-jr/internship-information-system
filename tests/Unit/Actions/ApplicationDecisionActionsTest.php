<?php

use App\Actions\DecideApplication;
use App\Actions\ScheduleInterview;
use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Notifications\ApplicationDecided;
use App\Notifications\InterviewScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

$slot = ['title' => 'Initial interview', 'venue' => 'Google Meet', 'link' => 'https://meet.google.com/abc', 'scheduled_on' => '2026-10-20', 'starts_at' => '10:00', 'ends_at' => '10:30', 'notes' => 'Bring your portfolio.'];

it('schedules an interview, moves the application to for-interview and notifies the intern', function () use ($slot) {
    Notification::fake();
    $application = Application::factory()->create();

    $interview = app(ScheduleInterview::class)($application, $slot);

    expect($application->refresh()->status)->toBe(ApplicationStatus::ForInterview)
        ->and($interview->scheduled_on->toDateString())->toBe('2026-10-20')->and($interview->starts_at)->toBe('10:00:00')->and($interview->venue)->toBe('Google Meet');
    Notification::assertSentTo($application->intern, InterviewScheduled::class, fn (InterviewScheduled $n) => str_contains($n->toArray($application->intern)['body'], 'Google Meet'));

    app(ScheduleInterview::class)($application, [...$slot, 'venue' => 'Zoom']);
    expect($application->interview()->count())->toBe(1)->and($application->interview->refresh()->venue)->toBe('Zoom');
});

it('refuses to schedule for decided applications', function () use ($slot) {
    foreach ([Application::factory()->accepted(), Application::factory()->declined()] as $factory) {
        $application = $factory->create();
        expect(fn () => app(ScheduleInterview::class)($application, $slot))->toThrow(DomainRuleViolation::class);
    }
});

it('accepts or declines an open application exactly once and notifies the intern', function () {
    Notification::fake();
    $application = Application::factory()->forInterview()->create();

    app(DecideApplication::class)($application, ApplicationStatus::Accepted);
    expect($application->refresh()->status)->toBe(ApplicationStatus::Accepted)->and($application->decided_at)->not->toBeNull();
    Notification::assertSentTo($application->intern, ApplicationDecided::class, fn (ApplicationDecided $n) => str_contains($n->toArray($application->intern)['body'], $application->posting->company->company_code));

    expect(fn () => app(DecideApplication::class)($application, ApplicationStatus::Declined, 'Changed our mind'))->toThrow(DomainRuleViolation::class);

    $other = Application::factory()->create();
    expect(fn () => app(DecideApplication::class)($other, ApplicationStatus::Declined, ''))->toThrow(DomainRuleViolation::class);
    app(DecideApplication::class)($other, ApplicationStatus::Declined, 'Position filled');
    expect($other->refresh()->status)->toBe(ApplicationStatus::Declined)->and($other->decline_reason)->toBe('Position filled');

    expect(fn () => app(DecideApplication::class)(Application::factory()->create(), ApplicationStatus::Pending))->toThrow(InvalidArgumentException::class);
});
