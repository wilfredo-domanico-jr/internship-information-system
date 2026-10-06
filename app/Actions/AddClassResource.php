<?php

namespace App\Actions;

use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AddClassResource
{
    public function __invoke(ClassSection $section, User $uploader, string $title, UploadedFile $file): ClassResource
    {
        $path = $file->storeAs("classroom/{$section->id}/resources", Str::uuid().'.pdf', 'local');

        return $section->resources()->create([
            'uploader_id' => $uploader->id,
            'title' => trim($title),
            'file_path' => $path,
        ]);
    }
}
