<?php

namespace App\Policies;

use App\Models\ClassFolder;
use App\Models\User;

class ClassFolderPolicy
{
    public function view(User $user, ClassFolder $folder): bool
    {
        return $user->isAdmin() || $folder->classSection->hasMember($user);
    }

    public function manage(User $user, ClassFolder $folder): bool
    {
        return $folder->classSection->isAdvisedBy($user);
    }

    /** Enrolled interns upload; locked folders still accept uploads (flagged late by the action). */
    public function submit(User $user, ClassFolder $folder): bool
    {
        return $folder->classSection->enrolls($user);
    }
}
