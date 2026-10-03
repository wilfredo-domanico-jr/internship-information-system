<?php

use App\Enums\ClassStatus;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists classes with adviser, schedule, intern count and join code', function () {
    $adviser = User::factory()->adviser()->create(['first_name' => 'Elsie', 'last_name' => 'Isip']);
    $section = ClassSection::factory()->for($adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'join_code' => 'SBIT4C26', 'school_year' => '2025-2026', 'day' => 'Monday']);
    User::factory()->intern()->count(2)->create()->each(fn ($u) => $u->internProfile()->update(['class_section_id' => $section->id]));
    ClassSection::factory()->unassigned()->create(['course_code' => 'IT401', 'school_year' => '2024-2025']);

    $this->actingAs($this->admin)->get(route('admin.classes.index'))
        ->assertOk()->assertSee('CC101')->assertSee('SBIT-4C')->assertSee('Elsie Isip')->assertSee('SBIT4C26')->assertSee('No adviser')->assertSee('Monday');
    $this->actingAs($this->admin)->get(route('admin.classes.index', ['q' => 'it401']))->assertSee('IT401')->assertDontSee('CC101');
    $this->actingAs($this->admin)->get(route('admin.classes.index', ['year' => '2025-2026']))->assertSee('CC101')->assertDontSee('IT401');
});

it('filters archived classes', function () {
    ClassSection::factory()->create(['course_code' => 'LIVE1']);
    ClassSection::factory()->archived()->create(['course_code' => 'OLD99']);

    $this->actingAs($this->admin)->get(route('admin.classes.index'))->assertSee('LIVE1')->assertSee('OLD99');
    $this->actingAs($this->admin)->get(route('admin.classes.index', ['status' => ClassStatus::Archived->value]))->assertSee('OLD99')->assertDontSee('LIVE1');
});

it('shows the roster with hours and links to interns', function () {
    $section = ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['class_section_id' => $section->id, 'student_number' => '21-0001', 'total_hours' => 88]);

    $this->actingAs($this->admin)->get(route('admin.classes.show', $section))
        ->assertOk()->assertSee('CC101 · SBIT-4C')->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('88')
        ->assertSee(route('admin.interns.show', $intern));
});
