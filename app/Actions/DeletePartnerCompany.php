<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class DeletePartnerCompany
{
    public function __invoke(Company $company): void
    {
        if ($company->placements()->exists() || $company->cosApplications()->exists()) {
            throw new DomainRuleViolation("{$company->name} has intern placements or applications and cannot be deleted.");
        }

        $logo = $company->logo_path;
        $company->delete();

        if ($logo && Storage::disk('public')->exists($logo)) {
            Storage::disk('public')->delete($logo);
        }
    }
}
