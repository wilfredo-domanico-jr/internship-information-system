<?php

use App\Models\Certificate;
use App\Models\Department;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternRemoved;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->company = $this->user->company;
});

it('lists active interns with hours tiers, departments and search', function () {
    $maria = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $maria->internProfile()->update(['student_number' => '21-0001', 'total_hours' => 300]);
    $dept = Department::factory()->create(['name' => 'Web Team']);
    Placement::factory()->for($maria, 'intern')->for($this->company)->create(['hours_rendered' => 300, 'department_id' => $dept->id]);
    $juan = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    Placement::factory()->for($juan, 'intern')->for($this->company)->create();
    Placement::factory()->for($this->company)->ended()->create(['intern_id' => User::factory()->intern()->create(['first_name' => 'Old', 'last_name' => 'Intern'])->id]);
    Placement::factory()->create(['intern_id' => User::factory()->intern()->create(['first_name' => 'Other', 'last_name' => 'Company'])->id]);

    $this->actingAs($this->user)->get(route('company.interns.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('Certificate eligible')->assertSee('Web Team')->assertSee('300 h')
        ->assertSee('Juan Cruz')->assertSee('Below certificate threshold')->assertDontSee('Old Intern')->assertDontSee('Other Company');
    $this->actingAs($this->user)->get(route('company.interns.index', ['q' => 'juan']))->assertSee('Juan Cruz')->assertDontSee('Maria Santos');
});

it('assigns a department and removes an intern from its own placements only', function () {
    Notification::fake();
    $mine = Placement::factory()->for($this->company)->create();
    $theirs = Placement::factory()->create();
    $dept = Department::factory()->create(['name' => 'QA']);

    $this->actingAs($this->user)->put(route('company.placements.department', $theirs), ['department_id' => $dept->id])->assertForbidden();
    $this->actingAs($this->user)->post(route('company.placements.remove', $theirs))->assertForbidden();

    $this->actingAs($this->user)->put(route('company.placements.department', $mine), ['department_id' => $dept->id])->assertRedirect()->assertSessionHas('success');
    expect($mine->refresh()->department_id)->toBe($dept->id);
    $this->actingAs($this->user)->put(route('company.placements.department', $mine), ['department_id' => 999])->assertSessionHasErrors('department_id');

    $this->actingAs($this->user)->post(route('company.placements.remove', $mine))->assertRedirect(route('company.interns.index'))->assertSessionHas('success');
    expect($mine->refresh()->ended_at)->not->toBeNull();
    Notification::assertSentTo($mine->intern, InternRemoved::class);
});

it('shows past placements in history with dates, hours and department', function () {
    $old = User::factory()->intern()->create(['first_name' => 'Old', 'last_name' => 'Intern']);
    $placement = Placement::factory()->for($old, 'intern')->for($this->company)->ended()->create(['hours_rendered' => 486, 'department_id' => Department::factory()->create(['name' => 'Web Team'])->id, 'started_at' => '2026-01-10', 'ended_at' => '2026-05-30']);
    Certificate::factory()->for($placement)->create();
    Placement::factory()->for($this->company)->create(['intern_id' => User::factory()->intern()->create(['first_name' => 'Still', 'last_name' => 'Here'])->id]);

    $this->actingAs($this->user)->get(route('company.history.index'))
        ->assertOk()->assertSee('Old Intern')->assertSee('Web Team')->assertSee('486 h')->assertSee('Jan 10, 2026')->assertSee('May 30, 2026')->assertSee('1 certificate')->assertDontSee('Still Here');
});
