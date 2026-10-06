<?php

namespace App\Policies;

use App\Models\Placement;
use App\Models\User;

class PlacementPolicy
{
    public function view(User $user, Placement $placement): bool
    {
        return $user->isAdmin() || $placement->isInternOf($user) || $placement->isManagedBy($user);
    }

    /** Assign a department, remove the intern, issue a certificate. */
    public function manage(User $user, Placement $placement): bool
    {
        return $placement->isManagedBy($user);
    }

    public function leave(User $user, Placement $placement): bool
    {
        return $placement->isInternOf($user) && $placement->isActive();
    }
}
