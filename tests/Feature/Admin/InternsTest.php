<?php

use App\Models\ClassSection;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists interns with class, placement and hours', function () {
    $section = ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['class_section_id' => $section->id, 'student_number' => '21-0001', 'total_hours' => 120]);
    Placement::factory()->for($intern, 'intern')->for(Company::factory()->partner()->create(['name' => 'City Hall']))->create();
    User::factory()->adviser()->create(['first_name' => 'NotAnIntern']);

    $this->actingAs($this->admin)->get(route('admin.interns.index'))
        ->assertOk()
        ->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('CC101')->assertSee('City Hall')->assertSee('120')
        ->assertDontSee('NotAnIntern');
});

it('searches by student number and filters by class and placement', function () {
    $section = ClassSection::factory()->create();
    $a = User::factory()->intern()->create(['first_name' => 'Alpha']);
    $a->internProfile()->update(['student_number' => '19-0842', 'class_section_id' => $section->id]);
    $b = User::factory()->intern()->create(['first_name' => 'Bravo']);
    Placement::factory()->for($b, 'intern')->for(Company::factory()->partner()->create())->create();

    $this->actingAs($this->admin)->get(route('admin.interns.index', ['q' => '0842']))->assertSee('Alpha')->assertDontSee('Bravo');
    $this->actingAs($this->admin)->get(route('admin.interns.index', ['class' => $section->id]))->assertSee('Alpha')->assertDontSee('Bravo');
    $this->actingAs($this->admin)->get(route('admin.interns.index', ['placement' => 'placed']))->assertSee('Bravo')->assertDontSee('Alpha');
    $this->actingAs($this->admin)->get(route('admin.interns.index', ['placement' => 'unplaced']))->assertSee('Alpha')->assertDontSee('Bravo');
});

it('shows an intern with progress and placement history', function () {
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['total_hours' => 243]);
    Placement::factory()->for($intern, 'intern')->for(Company::factory()->partner()->create(['name' => 'Old Place']))->ended()->create(['hours_rendered' => 100]);
    Placement::factory()->for($intern, 'intern')->for(Company::factory()->partner()->create(['name' => 'New Place']))->create(['hours_rendered' => 143]);

    $this->actingAs($this->admin)->get(route('admin.interns.show', $intern))
        ->assertOk()->assertSee('Maria Santos')->assertSee('50%')->assertSee('Old Place')->assertSee('New Place')->assertSee('243');
});

it('returns 404 for a non-intern user and 403 for non-admins', function () {
    $adviser = User::factory()->adviser()->create();
    $this->actingAs($this->admin)->get(route('admin.interns.show', $adviser))->assertNotFound();
    $this->actingAs($adviser)->get(route('admin.interns.index'))->assertForbidden();
});

it('matches search terms literally instead of as LIKE wildcards', function () {
    User::factory()->intern()->create(['first_name' => 'Percy']);
    User::factory()->intern()->create(['first_name' => 'A%B']);

    $this->actingAs($this->admin)->get(route('admin.interns.index', ['q' => '%']))
        ->assertSee('A%B', false)->assertDontSee('Percy');
    $this->actingAs($this->admin)->get(route('admin.interns.index', ['q' => '_']))
        ->assertDontSee('Percy')->assertDontSee('A%B', false);
});
