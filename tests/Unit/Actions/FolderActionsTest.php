<?php

use App\Actions\CreateFolder;
use App\Actions\DeleteFolder;
use App\Actions\ToggleFolderLock;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates an unlocked folder and toggles its lock', function () {
    $section = ClassSection::factory()->create();

    $folder = app(CreateFolder::class)($section, '  Weekly Report 1 ');
    expect($folder->name)->toBe('Weekly Report 1')->and($folder->is_locked)->toBeFalse()->and($folder->class_section_id)->toBe($section->id);

    app(ToggleFolderLock::class)($folder);
    expect($folder->refresh()->is_locked)->toBeTrue();
    app(ToggleFolderLock::class)($folder);
    expect($folder->refresh()->is_locked)->toBeFalse();
});

it('deletes a folder with its submissions and their files', function () {
    Storage::fake('local');
    $folder = ClassFolder::factory()->create();
    Storage::disk('local')->put('classroom/x/a.pdf', 'a');
    Storage::disk('local')->put('classroom/x/b.pdf', 'b');
    Storage::disk('local')->put('classroom/x/keep.pdf', 'k');
    ClassSubmission::factory()->for($folder, 'folder')->create(['file_path' => 'classroom/x/a.pdf']);
    ClassSubmission::factory()->for($folder, 'folder')->create(['file_path' => 'classroom/x/b.pdf']);
    $other = ClassSubmission::factory()->create(['file_path' => 'classroom/x/keep.pdf']);

    app(DeleteFolder::class)($folder);

    expect(ClassFolder::whereKey($folder->id)->exists())->toBeFalse()
        ->and(ClassSubmission::where('class_folder_id', $folder->id)->count())->toBe(0)
        ->and(ClassSubmission::whereKey($other->id)->exists())->toBeTrue();
    Storage::disk('local')->assertMissing('classroom/x/a.pdf');
    Storage::disk('local')->assertMissing('classroom/x/b.pdf');
    Storage::disk('local')->assertExists('classroom/x/keep.pdf');
});
