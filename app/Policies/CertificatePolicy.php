<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function view(User $user, Certificate $certificate): bool
    {
        return $user->isAdmin() || $certificate->placement->isInternOf($user) || $certificate->placement->isManagedBy($user);
    }
}
