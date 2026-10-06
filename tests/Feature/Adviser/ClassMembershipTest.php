<?php

use App\Models\Announcement;
use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('forbids a former adviser from posting, creating folders, uploading or reviewing', function () {
    Storage::fake('local');
    $section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $folder = ClassFolder::factory()->for($section)->create();
    $submission = ClassSubmission::factory()->for($folder, 'folder')->create();
    $this->actingAs($this->adviser)->post(route('adviser.classes.leave', $section))->assertRedirect();

    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $section), ['body' => '<p>Hi</p>'])->assertForbidden();
    $this->actingAs($this->adviser)->post(route('adviser.folders.store', $section), ['name' => 'New folder'])->assertForbidden();
    $this->actingAs($this->adviser)->post(route('adviser.folders.lock', $folder))->assertForbidden();
    $this->actingAs($this->adviser)->post(route('adviser.resources.store', $section), ['title' => 'Guide', 'file' => UploadedFile::fake()->create('g.pdf', 10, 'application/pdf')])->assertForbidden();
    $this->actingAs($this->adviser)->post(route('adviser.submissions.approve', $submission))->assertForbidden();

    expect(Announcement::count())->toBe(0)->and(ClassFolder::count())->toBe(1)->and($folder->refresh()->is_locked)->toBeFalse()
        ->and(ClassResource::count())->toBe(0)->and($submission->refresh()->status->value)->toBe('pending');
});
