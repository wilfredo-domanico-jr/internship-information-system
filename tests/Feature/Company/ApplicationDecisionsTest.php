<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\User;
use App\Notifications\ApplicationDecided;
use App\Notifications\InterviewScheduled;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->posting = InternshipPosting::factory()->for($this->user->company)->create();
    $this->application = Application::factory()->for($this->posting, 'posting')->create();
    $this->slot = ['title' => 'Initial interview', 'venue' => 'Google Meet', 'link' => 'https://meet.google.com/abc', 'scheduled_on' => now()->addDays(3)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '10:30', 'notes' => ''];
});

it('schedules an interview from the profile page', function () {
    Notification::fake();

    $this->actingAs($this->user)->get(route('company.applications.show', $this->application))->assertSee(route('company.applications.interview', $this->application))->assertSee(route('company.applications.decline', $this->application));

    $this->actingAs($this->user)->post(route('company.applications.interview', $this->application), $this->slot)
        ->assertRedirect(route('company.applications.show', $this->application))->assertSessionHas('success');

    expect($this->application->refresh()->status)->toBe(ApplicationStatus::ForInterview)->and($this->application->interview->venue)->toBe('Google Meet');
    Notification::assertSentTo($this->application->intern, InterviewScheduled::class);
});

it('validates the interview slot', function () {
    $this->actingAs($this->user)->post(route('company.applications.interview', $this->application), [...$this->slot, 'title' => '', 'scheduled_on' => now()->subDay()->toDateString(), 'ends_at' => '09:00', 'link' => 'not-a-url'])
        ->assertSessionHasErrors(['title', 'scheduled_on', 'ends_at', 'link']);
    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Pending);
});

it('declines with a reason and notifies the intern', function () {
    Notification::fake();

    $this->actingAs($this->user)->post(route('company.applications.decline', $this->application), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->user)->post(route('company.applications.decline', $this->application), ['reason' => 'Position filled'])
        ->assertRedirect(route('company.postings.applicants', $this->posting))->assertSessionHas('success');

    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Declined)->and($this->application->decline_reason)->toBe('Position filled');
    Notification::assertSentTo($this->application->intern, ApplicationDecided::class);
});

it('forbids other companies', function () {
    $other = User::factory()->company()->create();

    $this->actingAs($other)->post(route('company.applications.interview', $this->application), $this->slot)->assertForbidden();
    $this->actingAs($other)->post(route('company.applications.decline', $this->application), ['reason' => 'x'])->assertForbidden();
    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Pending);
});
