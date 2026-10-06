<?php

use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
    $folder = ClassFolder::factory()->for($this->section)->create();
    Storage::disk('local')->put('classroom/t/sub.pdf', '%PDF-1.4 fake');
    Storage::disk('local')->put('classroom/t/res.pdf', '%PDF-1.4 fake');
    $this->submission = ClassSubmission::factory()->for($folder, 'folder')->for($this->intern, 'intern')->create(['file_path' => 'classroom/t/sub.pdf']);
    $this->resource = ClassResource::factory()->for($this->section)->for($this->adviser, 'uploader')->create(['file_path' => 'classroom/t/res.pdf']);
});

it('serves a submission to its intern, the class adviser and admins', function () {
    foreach ([$this->intern, $this->adviser, User::factory()->admin()->create()] as $user) {
        $this->actingAs($user)->get(route('files.show', ['class-submission', $this->submission->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
});

it('serves a resource to class members and admins only', function () {
    $this->actingAs($this->intern)->get(route('files.show', ['class-resource', $this->resource->id]))->assertOk();
    $this->actingAs($this->adviser)->get(route('files.show', ['class-resource', $this->resource->id]))->assertOk();
    $this->actingAs(User::factory()->intern()->create())->get(route('files.show', ['class-resource', $this->resource->id]))->assertForbidden();
});

it('forbids submissions to outsiders', function () {
    $this->actingAs(User::factory()->intern()->create())->get(route('files.show', ['class-submission', $this->submission->id]))->assertForbidden();
    $this->actingAs(User::factory()->adviser()->create())->get(route('files.show', ['class-submission', $this->submission->id]))->assertForbidden();
    $this->actingAs(User::factory()->company()->create())->get(route('files.show', ['class-submission', $this->submission->id]))->assertForbidden();
});
