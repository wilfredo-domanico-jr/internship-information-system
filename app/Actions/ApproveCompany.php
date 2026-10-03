<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyApproved;

class ApproveCompany
{
    /** @return bool true when the status actually changed */
    public function __invoke(Company $company, User $admin): bool
    {
        if ($company->isApproved()) {
            return false;
        }

        $company->update([
            'approval_status' => ApprovalStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $company->user?->notify(new CompanyApproved($company));

        return true;
    }
}
