<?php

namespace App\Policies;

use App\Models\ClassSection;
use App\Models\User;

class ClassSectionPolicy
{
    /** Members (adviser, enrolled interns) and admins may open the class. */
    public function view(User $user, ClassSection $section): bool
    {
        return $user->isAdmin() || $section->hasMember($user);
    }

    /** Only the current adviser may change the class or anything inside it. */
    public function manage(User $user, ClassSection $section): bool
    {
        return $section->isAdvisedBy($user);
    }
}
