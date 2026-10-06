<?php

namespace App\Policies;

use App\Enums\DtrStatus;
use App\Models\Dtr;
use App\Models\User;

class DtrPolicy
{
    public function view(User $user, Dtr $dtr): bool
    {
        return $user->isAdmin() || $dtr->placement->isInternOf($user) || $dtr->placement->isManagedBy($user);
    }

    public function review(User $user, Dtr $dtr): bool
    {
        return $dtr->placement->isManagedBy($user);
    }

    /** Interns may withdraw a DTR only before it is reviewed. */
    public function delete(User $user, Dtr $dtr): bool
    {
        return $dtr->placement->isInternOf($user) && $dtr->status === DtrStatus::Pending;
    }
}
