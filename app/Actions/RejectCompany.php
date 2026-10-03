<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyRejected;

class RejectCompany
{
    /** @return bool true when the status actually changed */
    public function __invoke(Company $company, User $admin, ?string $reason = null): bool
    {
        if ($company->approval_status === ApprovalStatus::Rejected) {
            return false;
        }

        $company->update([
            'approval_status' => ApprovalStatus::Rejected,
            'approved_by' => $admin->id,
            'approved_at' => null,
        ]);

        $company->user?->notify(new CompanyRejected($company, $reason));

        return true;
    }
}
