<?php

use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(function () {
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
});

it('shows the join form when the intern has no active class', function () {
    $this->actingAs($this->intern)->get(route('intern.class.show'))
        ->assertOk()->assertSee('Join a class')->assertSee('name="join_code"', false);
    $this->actingAs($this->intern)->get(route('intern.class.people'))->assertRedirect(route('intern.class.show'));
});

it('joins a class by code and then sees its stream', function () {
    $section = ClassSection::factory()->create(['join_code' => 'SBIT4C26', 'course_code' => 'CC101', 'section' => 'SBIT-4C']);
    Announcement::factory()->for($section)->for($section->adviser, 'author')->create(['body' => '<p>Welcome to Practicum!</p>']);

    $this->actingAs($this->intern)->post(route('intern.class.join'), ['join_code' => 'sbit4c26'])
        ->assertRedirect(route('intern.class.show'))->assertSessionHas('success');

    $this->actingAs($this->intern)->get(route('intern.class.show'))
        ->assertOk()->assertSee('CC101 · SBIT-4C')->assertSee('Welcome to Practicum!')->assertSee($section->adviser->name)->assertDontSee('name="join_code"', false);
});

it('flashes an error when already enrolled or the code is unknown', function () {
    $current = ClassSection::factory()->create(['join_code' => 'FIRST001']);
    ClassSection::factory()->create(['join_code' => 'OTHER001']);
    $this->intern->internProfile->update(['class_section_id' => $current->id]);

    $this->actingAs($this->intern)->from(route('intern.class.show'))->post(route('intern.class.join'), ['join_code' => 'OTHER001'])
        ->assertRedirect(route('intern.class.show'))->assertSessionHas('error');
    expect($this->intern->internProfile->refresh()->class_section_id)->toBe($current->id);

    $free = User::factory()->intern()->create();
    $this->actingAs($free)->from(route('intern.class.show'))->post(route('intern.class.join'), ['join_code' => 'NOPE0000'])
        ->assertRedirect(route('intern.class.show'))->assertSessionHas('error');
});

it('lists the adviser and classmates on the people tab', function () {
    $section = ClassSection::factory()->create();
    $this->intern->internProfile->update(['class_section_id' => $section->id, 'student_number' => '21-0001']);
    $mate = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    $mate->internProfile()->update(['class_section_id' => $section->id, 'student_number' => '21-0002']);
    User::factory()->intern()->create(['first_name' => 'Nobody', 'last_name' => 'Else']);

    $this->actingAs($this->intern)->get(route('intern.class.people'))
        ->assertOk()->assertSee($section->adviser->name)->assertSee('Juan Cruz')->assertSee('21-0002')->assertSee('Maria Santos')->assertDontSee('Nobody Else');
});

it('links the dashboards to the class pages', function () {
    $section = ClassSection::factory()->create();
    $this->intern->internProfile->update(['class_section_id' => $section->id]);

    $this->actingAs($this->intern)->get(route('intern.dashboard'))->assertSee(route('intern.class.show'));
    $this->actingAs($section->adviser)->get(route('adviser.dashboard'))->assertSee(route('adviser.classes.show', $section));
});
