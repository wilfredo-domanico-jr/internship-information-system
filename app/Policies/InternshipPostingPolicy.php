<?php

namespace App\Policies;

use App\Enums\PostingStatus;
use App\Models\InternshipPosting;
use App\Models\User;

class InternshipPostingPolicy
{
    /** Its company and admins always; interns only while the posting is open and its company is active. */
    public function view(User $user, InternshipPosting $posting): bool
    {
        return $user->isAdmin()
            || $posting->company->isManagedBy($user)
            || ($user->isIntern() && $posting->status === PostingStatus::Open && $posting->companyIsOpenForBusiness());
    }

    public function manage(User $user, InternshipPosting $posting): bool
    {
        return $posting->company->isManagedBy($user);
    }
}
