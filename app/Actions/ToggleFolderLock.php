<?php

namespace App\Actions;

use App\Models\ClassFolder;

/** Locking does not block uploads; it marks later uploads as late. */
class ToggleFolderLock
{
    public function __invoke(ClassFolder $folder): ClassFolder
    {
        $folder->update(['is_locked' => ! $folder->is_locked]);

        return $folder;
    }
}
