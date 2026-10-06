<?php

namespace App\Actions;

use App\Enums\AccountStatus;
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
        $company = Company::registered()->approved()
            ->whereHas('user', fn ($q) => $q->where('status', AccountStatus::Active))
            ->where('company_code', Str::upper(trim($companyCode)))->first();

        if (! $company) {
            throw new DomainRuleViolation('No company has that code.');
        }

        $placement = DB::transaction(function () use ($intern, $company) {
            // Serialise concurrent submits for the same intern.
            User::query()->whereKey($intern->id)->lockForUpdate()->first();

            if ($intern->activePlacement()->exists()) {
                throw new DomainRuleViolation('You are already placed with a company. Leave it before joining another.');
            }

            $previous = Placement::query()->where('intern_id', $intern->id)->where('company_id', $company->id)
                ->whereNotNull('ended_at')->latest('ended_at')->first();

            $accepted = Application::query()->where('intern_id', $intern->id)->where('status', ApplicationStatus::Accepted)
                ->whereHas('posting', fn ($q) => $q->where('company_id', $company->id))
                ->when($previous, fn ($q) => $q->where('decided_at', '>', $previous->ended_at->copy()->endOfDay()))
                ->exists();

            if (! $accepted) {
                throw new DomainRuleViolation($previous
                    ? "You need a new acceptance from {$company->name} before you can rejoin it."
                    : "You need an accepted application from {$company->name} before you can join it.");
            }

            return Placement::create([
                'intern_id' => $intern->id,
                'company_id' => $company->id,
                'department_id' => null,
                'started_at' => today(),
                'ended_at' => null,
                'hours_rendered' => 0,
                'absences' => 0,
            ]);
        });

        $company->user?->notify(new InternJoinedCompany($placement->load('intern')));

        return $placement;
    }
}
