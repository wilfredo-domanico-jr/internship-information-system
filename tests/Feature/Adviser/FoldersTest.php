<?php

use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
});

it('lists folders with lock state and pending counts on the documents tab', function () {
    $open = ClassFolder::factory()->for($this->section)->create(['name' => 'Endorsement Letter']);
    ClassFolder::factory()->for($this->section)->locked()->create(['name' => 'Weekly Report 1']);
    ClassSubmission::factory()->for($open, 'folder')->count(2)->create();
    ClassSubmission::factory()->for($open, 'folder')->approved()->create();

    $this->actingAs($this->adviser)->get(route('adviser.classes.documents', $this->section))
        ->assertOk()->assertSee('Endorsement Letter')->assertSee('Weekly Report 1')->assertSee('Locked')->assertSee('2 pending')->assertSee('Documents');
    $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))->assertSee(route('adviser.classes.documents', $this->section));
});

it('creates folders with unique names per class', function () {
    $this->actingAs($this->adviser)->post(route('adviser.folders.store', $this->section), ['name' => 'Resume'])
        ->assertRedirect(route('adviser.classes.documents', $this->section))->assertSessionHas('success');
    expect($this->section->folders()->where('name', 'Resume')->exists())->toBeTrue();

    $this->actingAs($this->adviser)->post(route('adviser.folders.store', $this->section), ['name' => 'Resume'])->assertSessionHasErrors('name');
    $this->actingAs($this->adviser)->post(route('adviser.folders.store', ClassSection::factory()->create()), ['name' => 'Resume'])->assertForbidden();
});

it('locks, unlocks and deletes folders it manages', function () {
    Storage::fake('local');
    $folder = ClassFolder::factory()->for($this->section)->create();
    Storage::disk('local')->put('classroom/x/a.pdf', 'a');
    ClassSubmission::factory()->for($folder, 'folder')->create(['file_path' => 'classroom/x/a.pdf']);
    $foreign = ClassFolder::factory()->create();

    $this->actingAs($this->adviser)->post(route('adviser.folders.lock', $folder))->assertRedirect();
    expect($folder->refresh()->is_locked)->toBeTrue();
    $this->actingAs($this->adviser)->post(route('adviser.folders.lock', $folder))->assertRedirect();
    expect($folder->refresh()->is_locked)->toBeFalse();

    $this->actingAs($this->adviser)->post(route('adviser.folders.lock', $foreign))->assertForbidden();
    $this->actingAs($this->adviser)->delete(route('adviser.folders.destroy', $foreign))->assertForbidden();

    $this->actingAs($this->adviser)->delete(route('adviser.folders.destroy', $folder))->assertRedirect(route('adviser.classes.documents', $this->section));
    expect(ClassFolder::whereKey($folder->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('classroom/x/a.pdf');
});

it('groups submissions by status and late on the folder page', function () {
    $folder = ClassFolder::factory()->for($this->section)->create(['name' => 'Endorsement Letter']);
    $pending = ClassSubmission::factory()->for($folder, 'folder')->create(['title' => 'Pending doc']);
    $late = ClassSubmission::factory()->for($folder, 'folder')->create(['title' => 'Late doc', 'is_late' => true]);
    $approved = ClassSubmission::factory()->for($folder, 'folder')->approved()->create(['title' => 'Approved doc']);
    $declined = ClassSubmission::factory()->for($folder, 'folder')->declined()->create(['title' => 'Declined doc', 'reviewer_note' => 'Wrong file']);

    $page = fn (string $status) => $this->actingAs($this->adviser)->get(route('adviser.folders.show', [$folder, 'status' => $status]))->assertOk();

    expect(substr_count($page('late')->getContent(), 'data-color="rose"'))->toBe(1);
    expect(substr_count($page('approved')->getContent(), 'data-color="rose"'))->toBe(0);
    $page('pending')->assertSee('Pending doc')->assertSee('Late doc')->assertDontSee('Approved doc')->assertSee($pending->intern->name);
    $page('approved')->assertSee('Approved doc')->assertDontSee('Pending doc');
    $page('declined')->assertSee('Declined doc')->assertSee('Wrong file');
    $page('late')->assertSee('Late doc')->assertDontSee('Pending doc');
    $this->actingAs($this->adviser)->get(route('adviser.folders.show', $folder))->assertSee('Pending doc')->assertSee(route('files.show', ['class-submission', $pending->id]));
    $this->actingAs($this->adviser)->get(route('adviser.folders.show', [$folder, 'status' => 'bogus']))->assertNotFound();
    $this->actingAs(User::factory()->adviser()->create())->get(route('adviser.folders.show', $folder))->assertForbidden();
});
