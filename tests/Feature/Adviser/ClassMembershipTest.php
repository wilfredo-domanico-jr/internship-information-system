<?php

use App\Models\ClassSection;
use App\Models\User;

beforeEach(fn () => $this->adviser = User::factory()->adviser()->create());

it('claims a class from the join form and shows it in the list', function () {
    ClassSection::factory()->unassigned()->create(['join_code' => 'SBIT4C26', 'course_code' => 'CC101', 'section' => 'SBIT-4C']);

    $this->actingAs($this->adviser)->get(route('adviser.classes.index'))->assertOk()->assertSee('name="join_code"', false);

    $this->actingAs($this->adviser)->post(route('adviser.classes.join'), ['join_code' => 'sbit4c26'])
        ->assertRedirect(route('adviser.classes.index'))->assertSessionHas('success');

    $this->actingAs($this->adviser)->get(route('adviser.classes.index'))->assertSee('CC101 · SBIT-4C');
});

it('flashes an error for a taken code and validates the input', function () {
    ClassSection::factory()->create(['join_code' => 'TAKEN001']);

    $this->actingAs($this->adviser)->from(route('adviser.classes.index'))->post(route('adviser.classes.join'), ['join_code' => 'TAKEN001'])
        ->assertRedirect(route('adviser.classes.index'))->assertSessionHas('error');
    $this->actingAs($this->adviser)->post(route('adviser.classes.join'), ['join_code' => ''])->assertSessionHasErrors('join_code');
});

it('leaves a class, lists it under past classes and loses access to it', function () {
    $section = ClassSection::factory()->for($this->adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C']);

    $this->actingAs($this->adviser)->post(route('adviser.classes.leave', $section))
        ->assertRedirect(route('adviser.classes.index'))->assertSessionHas('success');

    expect($section->refresh()->adviser_id)->toBeNull();
    $this->actingAs($this->adviser)->get(route('adviser.classes.index'))->assertSee('Past classes')->assertSee('CC101 · SBIT-4C');
    $this->actingAs($this->adviser)->get(route('adviser.classes.edit', $section))->assertForbidden();
});

it('cannot leave someone else’s class', function () {
    $section = ClassSection::factory()->create();

    $this->actingAs($this->adviser)->post(route('adviser.classes.leave', $section))->assertForbidden();
});
