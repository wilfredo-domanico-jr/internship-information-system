<?php

namespace App\Policies;

use App\Models\ClassResource;
use App\Models\User;

class ClassResourcePolicy
{
    public function view(User $user, ClassResource $resource): bool
    {
        return $user->isAdmin() || $resource->classSection->hasMember($user);
    }

    public function delete(User $user, ClassResource $resource): bool
    {
        return $resource->classSection->isAdvisedBy($user);
    }
}
