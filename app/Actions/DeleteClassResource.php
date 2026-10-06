<?php

namespace App\Actions;

use App\Models\ClassResource;
use Illuminate\Support\Facades\Storage;

class DeleteClassResource
{
    public function __invoke(ClassResource $resource): void
    {
        $path = $resource->file_path;

        $resource->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
