<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $user->isAdmin() || $application->isOwnedBy($user) || $application->isManagedBy($user);
    }

    /** Schedule an interview, accept or decline. */
    public function decide(User $user, Application $application): bool
    {
        return $application->isManagedBy($user);
    }

    /** The intern may withdraw while the application is still open. */
    public function cancel(User $user, Application $application): bool
    {
        return $application->isOwnedBy($user) && $application->status->isOpen();
    }
}
