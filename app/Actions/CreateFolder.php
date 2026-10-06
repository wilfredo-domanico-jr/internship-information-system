<?php

namespace App\Actions;

use App\Models\ClassFolder;
use App\Models\ClassSection;

class CreateFolder
{
    public function __invoke(ClassSection $section, string $name): ClassFolder
    {
        return $section->folders()->create(['name' => trim($name), 'is_locked' => false]);
    }
}
