<?php

use App\Models\Application;
use App\Models\ClassSection;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\Placement;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->posting = InternshipPosting::factory()->for($this->user->company)->create(['title' => 'Web Dev Intern']);
});

it('lists unplaced applicants grouped by status', function () {
    $pending = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $pending->internProfile()->update(['student_number' => '21-0001', 'class_section_id' => ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C'])->id]);
    Application::factory()->for($this->posting, 'posting')->for($pending, 'intern')->create();
    $placed = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Placed']);
    Placement::factory()->for($placed, 'intern')->create();
    Application::factory()->for($this->posting, 'posting')->for($placed, 'intern')->create();
    $interviewee = User::factory()->intern()->create(['first_name' => 'Ana', 'last_name' => 'Interview']);
    Application::factory()->for($this->posting, 'posting')->for($interviewee, 'intern')->forInterview()->create();

    $this->actingAs($this->user)->get(route('company.postings.applicants', $this->posting))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('CC101 · SBIT-4C')
        ->assertDontSee('Juan Placed')->assertDontSee('Ana Interview');
    $this->actingAs($this->user)->get(route('company.postings.applicants', [$this->posting, 'status' => 'for_interview']))
        ->assertSee('Ana Interview')->assertDontSee('Maria Santos');
    $this->actingAs($this->user)->get(route('company.postings.applicants', [$this->posting, 'status' => 'bogus']))->assertNotFound();
    $this->actingAs(User::factory()->company()->create())->get(route('company.postings.applicants', $this->posting))->assertForbidden();
});

it('shows an applicant profile with documents, hours and interview details', function () {
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com']);
    $intern->internProfile()->update(['student_number' => '21-0001', 'about' => 'Eager to learn.', 'total_hours' => 120]);
    $application = Application::factory()->for($this->posting, 'posting')->for($intern, 'intern')->forInterview()->create();
    Interview::factory()->for($application)->create(['venue' => 'Google Meet']);

    $this->actingAs($this->user)->get(route('company.applications.show', $application))
        ->assertOk()->assertSee('Maria Santos')->assertSee('maria@example.com')->assertSee('21-0001')->assertSee('Eager to learn.')->assertSee('120')
        ->assertSee(route('files.show', ['application-resume', $application->id]))->assertSee(route('files.show', ['application-endorsement', $application->id]))
        ->assertSee('Google Meet')->assertSee('For Interview');

    $this->actingAs(User::factory()->company()->create())->get(route('company.applications.show', $application))->assertForbidden();
});
