<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\User;

beforeEach(fn () => $this->intern = User::factory()->intern()->create());

it('lists the intern’s applications with their timelines and cancel buttons', function () {
    $posting = InternshipPosting::factory()->create(['title' => 'Web Dev Intern']);
    $mine = Application::factory()->for($posting, 'posting')->for($this->intern, 'intern')->forInterview()->create();
    Interview::factory()->for($mine)->create(['venue' => 'Zoom']);
    $declined = Application::factory()->for($this->intern, 'intern')->declined()->create(['decline_reason' => 'Position filled']);
    Application::factory()->create(['intern_id' => User::factory()->intern()->create()->id]);

    $this->actingAs($this->intern)->get(route('intern.applications.index'))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee($posting->company->name)->assertSee('Zoom')->assertSee('Position filled')
        ->assertSee(route('intern.applications.cancel', $mine))->assertDontSee(route('intern.applications.cancel', $declined))
        ->assertSee(route('files.show', ['application-resume', $mine->id]));
});

it('cancels an open application and refuses others', function () {
    $mine = Application::factory()->for($this->intern, 'intern')->create();
    $theirs = Application::factory()->create();
    $accepted = Application::factory()->for($this->intern, 'intern')->accepted()->create();

    $this->actingAs($this->intern)->post(route('intern.applications.cancel', $theirs))->assertForbidden();
    $this->actingAs($this->intern)->post(route('intern.applications.cancel', $accepted))->assertForbidden();
    $this->actingAs($this->intern)->post(route('intern.applications.cancel', $mine))->assertRedirect(route('intern.applications.index'))->assertSessionHas('success');

    expect($mine->refresh()->status)->toBe(ApplicationStatus::Cancelled);
});
