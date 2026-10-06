<?php

use App\Actions\AddClassResource;
use App\Actions\DeleteClassResource;
use App\Models\ClassResource;
use App\Models\ClassSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores a resource PDF privately and deletes it with the record', function () {
    Storage::fake('local');
    $section = ClassSection::factory()->create();

    $resource = app(AddClassResource::class)($section, $section->adviser, ' OJT Guidelines ', UploadedFile::fake()->create('guide.pdf', 50, 'application/pdf'));

    expect($resource->title)->toBe('OJT Guidelines')->and($resource->uploader_id)->toBe($section->adviser_id)
        ->and($resource->file_path)->toStartWith("classroom/{$section->id}/resources/")->toEndWith('.pdf');
    Storage::disk('local')->assertExists($resource->file_path);

    $path = $resource->file_path;
    app(DeleteClassResource::class)($resource);
    expect(ClassResource::whereKey($resource->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
});
