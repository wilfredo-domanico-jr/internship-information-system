<?php

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassSection;
use App\Models\Placement;
use App\Models\User;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'join_code' => 'SBIT4C26']);
});

it('shows the stream with announcements and comments', function () {
    $announcement = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create(['body' => '<p>Upload your <strong>endorsement</strong> letter.</p><script>alert(1)</script>']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    AnnouncementComment::factory()->for($announcement)->for($intern, 'author')->create(['body' => 'Noted, thank you!']);

    $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))
        ->assertOk()->assertSee('CC101 · SBIT-4C')->assertSee('SBIT4C26')->assertSee('Stream')->assertSee('People')
        ->assertSee('<strong>endorsement</strong>', false)->assertDontSee('alert(1)', false)
        ->assertSee('Maria Santos')->assertSee('Noted, thank you!');
});

it('lists interns with hours, company and progress tier, and prints the roster', function () {
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['class_section_id' => $this->section->id, 'student_number' => '21-0001', 'total_hours' => 300]);
    Placement::factory()->for($intern, 'intern')->create(['hours_rendered' => 300]);
    $unplaced = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    $unplaced->internProfile()->update(['class_section_id' => $this->section->id, 'student_number' => '21-0002']);

    $this->actingAs($this->adviser)->get(route('adviser.classes.people', $this->section))
        ->assertOk()->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('300 / '.config('wiis.hours.required'))
        ->assertSee($intern->activePlacement->company->name)->assertSee('Certificate eligible')->assertSee('Juan Cruz')->assertSee('Below certificate threshold');

    $this->actingAs($this->adviser)->get(route('adviser.classes.print', $this->section))
        ->assertOk()->assertSee('window.print()')->assertSee(config('wiis.institution.name'))->assertSee('Santos, Maria')->assertSee('Cruz, Juan')->assertSee('21-0002');
});

it('forbids advisers who do not advise the class', function () {
    $other = User::factory()->adviser()->create();

    $this->actingAs($other)->get(route('adviser.classes.show', $this->section))->assertForbidden();
    $this->actingAs($other)->get(route('adviser.classes.people', $this->section))->assertForbidden();
    $this->actingAs($other)->get(route('adviser.classes.print', $this->section))->assertForbidden();
});
