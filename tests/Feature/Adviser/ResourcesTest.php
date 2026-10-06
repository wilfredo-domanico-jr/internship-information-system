<?php

use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
});

it('uploads a resource that interns can see and download', function () {
    $this->actingAs($this->adviser)->post(route('adviser.resources.store', $this->section), [
        'title' => 'OJT Guidelines', 'file' => UploadedFile::fake()->create('guide.pdf', 50, 'application/pdf'),
    ])->assertRedirect(route('adviser.classes.documents', $this->section))->assertSessionHas('success');

    $resource = ClassResource::firstOrFail();
    $this->actingAs($this->adviser)->get(route('adviser.classes.documents', $this->section))->assertSee('OJT Guidelines')->assertSee(route('files.show', ['class-resource', $resource->id]));
    $this->actingAs($this->intern)->get(route('intern.class.documents'))->assertSee('OJT Guidelines')->assertSee(route('files.show', ['class-resource', $resource->id]));
    $this->actingAs($this->intern)->get(route('files.show', ['class-resource', $resource->id]))->assertOk();
});

it('validates the upload and forbids other advisers', function () {
    $this->actingAs($this->adviser)->post(route('adviser.resources.store', $this->section), [
        'title' => '', 'file' => UploadedFile::fake()->create('guide.txt', 5, 'text/plain'),
    ])->assertSessionHasErrors(['title', 'file']);

    $this->actingAs(User::factory()->adviser()->create())->post(route('adviser.resources.store', $this->section), [
        'title' => 'X', 'file' => UploadedFile::fake()->create('g.pdf', 5, 'application/pdf'),
    ])->assertForbidden();
    expect(ClassResource::count())->toBe(0);
});

it('deletes its own resources and their files', function () {
    Storage::disk('local')->put('classroom/x/r.pdf', 'r');
    $resource = ClassResource::factory()->for($this->section)->for($this->adviser, 'uploader')->create(['file_path' => 'classroom/x/r.pdf']);
    $foreign = ClassResource::factory()->create();

    $this->actingAs($this->adviser)->delete(route('adviser.resources.destroy', $foreign))->assertForbidden();
    $this->actingAs($this->adviser)->delete(route('adviser.resources.destroy', $resource))->assertRedirect(route('adviser.classes.documents', $this->section));

    expect(ClassResource::whereKey($resource->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('classroom/x/r.pdf');
});
