<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternJoinedCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Acceptance never places an intern; entering the company code does (spec). */
class PlaceIntern
{
    public function __invoke(User $intern, string $companyCode): Placement
    {
        $company = Company::registered()->approved()->where('company_code', Str::upper(trim($companyCode)))->first();

        if (! $company) {
            throw new DomainRuleViolation('No company has that code.');
        }

        if ($intern->hasActivePlacement()) {
            throw new DomainRuleViolation('You are already placed with a company. Leave it before joining another.');
        }

        $accepted = Application::query()->where('intern_id', $intern->id)->where('status', ApplicationStatus::Accepted)
            ->whereHas('posting', fn ($q) => $q->where('company_id', $company->id))->exists();

        if (! $accepted) {
            throw new DomainRuleViolation("You need an accepted application from {$company->name} before you can join it.");
        }

        $placement = DB::transaction(fn () => Placement::create([
            'intern_id' => $intern->id,
            'company_id' => $company->id,
            'department_id' => null,
            'started_at' => today(),
            'ended_at' => null,
            'hours_rendered' => 0,
            'absences' => 0,
        ]));

        $company->user?->notify(new InternJoinedCompany($placement->load('intern')));

        return $placement;
    }
}
