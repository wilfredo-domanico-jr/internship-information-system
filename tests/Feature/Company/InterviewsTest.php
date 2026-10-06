<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\ApplicationDecided;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->posting = InternshipPosting::factory()->for($this->user->company)->create(['title' => 'Web Dev Intern']);
});

it('lists upcoming and past interviews for the company', function () {
    $soon = Application::factory()->for($this->posting, 'posting')->for(User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']), 'intern')->forInterview()->create();
    Interview::factory()->for($soon)->create(['scheduled_on' => now()->addDays(2)->toDateString(), 'venue' => 'Google Meet']);
    $past = Application::factory()->for($this->posting, 'posting')->for(User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']), 'intern')->forInterview()->create();
    Interview::factory()->for($past)->create(['scheduled_on' => now()->subDays(2)->toDateString()]);
    $foreign = Application::factory()->forInterview()->create(['intern_id' => User::factory()->intern()->create(['first_name' => 'Not', 'last_name' => 'Mine'])->id]);
    Interview::factory()->for($foreign)->create();

    $this->actingAs($this->user)->get(route('company.interviews.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('Google Meet')->assertSee('Web Dev Intern')->assertDontSee('Juan Cruz')->assertDontSee('Not Mine')
        ->assertSee(route('company.applications.accept', $soon))->assertSee(route('company.applications.decline', $soon));
    $this->actingAs($this->user)->get(route('company.interviews.index', ['when' => 'past']))->assertSee('Juan Cruz')->assertDontSee('Maria Santos');
});

it('accepts an applicant without placing them and tells them the company code', function () {
    Notification::fake();
    $application = Application::factory()->for($this->posting, 'posting')->forInterview()->create();

    $this->actingAs($this->user)->from(route('company.interviews.index'))->post(route('company.applications.accept', $application))
        ->assertRedirect(route('company.interviews.index'))->assertSessionHas('success');

    expect($application->refresh()->status)->toBe(ApplicationStatus::Accepted)->and(Placement::count())->toBe(0);
    Notification::assertSentTo($application->intern, ApplicationDecided::class, fn (ApplicationDecided $n) => str_contains($n->toArray($application->intern)['body'], $this->user->company->company_code));

    $this->actingAs($this->user)->from(route('company.interviews.index'))->post(route('company.applications.accept', $application))->assertRedirect()->assertSessionHas('error');
    $this->actingAs(User::factory()->company()->create())->post(route('company.applications.accept', $application))->assertForbidden();
});

it('does not list for-interview applicants who are already placed elsewhere', function () {
    $placed = User::factory()->intern()->create(['first_name' => 'Placed', 'last_name' => 'Elsewhere']);
    Placement::factory()->for($placed, 'intern')->create();
    $application = Application::factory()->for($this->posting, 'posting')->for($placed, 'intern')->forInterview()->create();
    Interview::factory()->for($application)->create(['scheduled_on' => now()->addDay()->toDateString()]);

    $this->actingAs($this->user)->get(route('company.interviews.index'))->assertOk()->assertDontSee('Placed Elsewhere');
});
