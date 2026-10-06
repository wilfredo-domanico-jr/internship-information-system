<?php

use App\Enums\ClassStatus;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(fn () => $this->adviser = User::factory()->adviser()->create());

it('lists only the active classes the adviser advises', function () {
    $mine = ClassSection::factory()->for($this->adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'join_code' => 'MINE0001']);
    ClassSection::factory()->create(['course_code' => 'XX999']);
    ClassSection::factory()->for($this->adviser, 'adviser')->archived()->create(['course_code' => 'OLD111']);
    User::factory()->intern()->count(2)->create()->each(fn ($u) => $u->internProfile()->update(['class_section_id' => $mine->id]));

    $this->actingAs($this->adviser)->get(route('adviser.classes.index'))
        ->assertOk()->assertSee('CC101 · SBIT-4C')->assertSee('MINE0001')->assertSee('Interns')
        ->assertDontSee('XX999')->assertDontSee('OLD111');
});

it('creates a class with a join code and an adviser log', function () {
    $this->actingAs($this->adviser)->get(route('adviser.classes.create'))->assertOk()->assertSee('Course code');

    $this->actingAs($this->adviser)->post(route('adviser.classes.store'), [
        'course_code' => ' cc101 ', 'subject' => 'Practicum', 'section' => 'sbit-4c', 'day' => 'Monday',
        'starts_at' => '08:00', 'ends_at' => '12:00', 'school_year' => '2025-2026',
    ])->assertRedirect(route('adviser.classes.index'))->assertSessionHas('success');

    $section = ClassSection::firstOrFail();
    expect($section->course_code)->toBe('CC101')->and($section->section)->toBe('SBIT-4C')
        ->and($section->adviser_id)->toBe($this->adviser->id)->and($section->join_code)->toHaveLength(8)
        ->and($section->status)->toBe(ClassStatus::Active)
        ->and(ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $this->adviser->id)->whereNull('left_at')->exists())->toBeTrue();
});

it('validates the schedule and refuses duplicate active classes', function () {
    ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'school_year' => '2025-2026']);
    $payload = ['course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'Funday', 'starts_at' => '13:00', 'ends_at' => '12:00', 'school_year' => '2025'];

    $this->actingAs($this->adviser)->post(route('adviser.classes.store'), $payload)
        ->assertSessionHasErrors(['day', 'ends_at', 'school_year']);
    $this->actingAs($this->adviser)->post(route('adviser.classes.store'), [...$payload, 'day' => 'Monday', 'ends_at' => '15:00', 'school_year' => '2025-2026'])
        ->assertSessionHasErrors('section');

    expect(ClassSection::count())->toBe(1);
});

it('edits only its own classes', function () {
    $mine = ClassSection::factory()->for($this->adviser, 'adviser')->create(['subject' => 'Practicum']);
    $other = ClassSection::factory()->create();

    $this->actingAs($this->adviser)->get(route('adviser.classes.edit', $mine))->assertOk()->assertSee('Practicum');
    $this->actingAs($this->adviser)->get(route('adviser.classes.edit', $other))->assertForbidden();
    $this->actingAs($this->adviser)->put(route('adviser.classes.update', $other), ['subject' => 'Hacked'])->assertForbidden();

    $this->actingAs($this->adviser)->put(route('adviser.classes.update', $mine), [
        'course_code' => $mine->course_code, 'subject' => 'Internship 1', 'section' => $mine->section, 'day' => 'Friday',
        'starts_at' => '09:30', 'ends_at' => '11:30', 'school_year' => $mine->school_year,
    ])->assertRedirect(route('adviser.classes.index'));

    expect($mine->refresh()->subject)->toBe('Internship 1')->and($mine->day)->toBe('Friday')->and($mine->starts_at)->toBe('09:30:00');
});

it('lets a class keep its own identity when edited', function () {
    $mine = ClassSection::factory()->for($this->adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'school_year' => '2025-2026']);

    $this->actingAs($this->adviser)->put(route('adviser.classes.update', $mine), [
        'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'Monday',
        'starts_at' => '08:00', 'ends_at' => '12:00', 'school_year' => '2025-2026',
    ])->assertSessionHasNoErrors();
});
