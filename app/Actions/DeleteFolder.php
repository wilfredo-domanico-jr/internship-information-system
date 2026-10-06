<?php

namespace App\Actions;

use App\Models\ClassFolder;
use Illuminate\Support\Facades\Storage;

class DeleteFolder
{
    public function __invoke(ClassFolder $folder): void
    {
        $paths = $folder->submissions()->pluck('file_path')->filter()->values()->all();

        $folder->delete(); // submissions cascade at the database level

        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }
}
