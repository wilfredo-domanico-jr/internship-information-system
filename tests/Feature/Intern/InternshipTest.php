<?php

use App\Models\Application;
use App\Models\Company;
use App\Models\Department;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternJoinedCompany;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->intern = User::factory()->intern()->create();
    $this->company = Company::factory()->registered()->create(['name' => 'TechNova', 'company_code' => 'TECHNOVA', 'address' => 'Ortigas Center']);
    $this->posting = InternshipPosting::factory()->for($this->company)->create();
});

it('shows the join form with accepted companies when unplaced', function () {
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create();

    $this->actingAs($this->intern)->get(route('intern.internship.show'))
        ->assertOk()->assertSee('name="company_code"', false)->assertSee('TechNova')->assertSee('accepted');
});

it('joins a company by code and then shows the placement with co-interns and the leave warning', function () {
    Notification::fake();
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create();
    $mate = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    Placement::factory()->for($mate, 'intern')->for($this->company)->create();

    $this->actingAs($this->intern)->post(route('intern.internship.join'), ['company_code' => 'technova'])
        ->assertRedirect(route('intern.internship.show'))->assertSessionHas('success');
    Notification::assertSentTo($this->company->user, InternJoinedCompany::class);

    $this->actingAs($this->intern)->get(route('intern.internship.show'))
        ->assertOk()->assertSee('TechNova')->assertSee('Ortigas Center')->assertSee('Juan Cruz')->assertSee('0 h')
        ->assertSee((string) config('wiis.hours.certificate_min'))->assertSee(route('intern.internship.leave'))->assertDontSee('name="company_code"', false);
});

it('flashes an error for bad codes and leaves with a notification', function () {
    $this->actingAs($this->intern)->from(route('intern.internship.show'))->post(route('intern.internship.join'), ['company_code' => 'TECHNOVA'])
        ->assertRedirect(route('intern.internship.show'))->assertSessionHas('error');
    $this->actingAs($this->intern)->post(route('intern.internship.join'), ['company_code' => ''])->assertSessionHasErrors('company_code');

    $placement = Placement::factory()->for($this->intern, 'intern')->for($this->company)->create(['department_id' => Department::factory()->create(['name' => 'Web Team'])->id, 'hours_rendered' => 300]);
    $this->actingAs($this->intern)->get(route('intern.internship.show'))->assertSee('Web Team')->assertSee('300 h')->assertSee('eligible');

    $this->actingAs($this->intern)->post(route('intern.internship.leave'))->assertRedirect(route('intern.internship.show'))->assertSessionHas('success');
    expect($placement->refresh()->ended_at)->not->toBeNull();
    $this->actingAs($this->intern)->post(route('intern.internship.leave'))->assertForbidden();
});

it('links the dashboard to My internship', function () {
    $this->actingAs($this->intern)->get(route('intern.dashboard'))->assertSee(route('intern.internship.show'));
});
