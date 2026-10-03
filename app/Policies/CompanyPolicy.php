<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /** Permit and MOA are visible to admins and to the company's own account. */
    public function viewDocuments(User $user, Company $company): bool
    {
        return $user->isAdmin() || $company->user_id === $user->id;
    }
}
