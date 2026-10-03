<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;

class AdminDashboardStats
{
    /** @return array{interns:int, advisers:int, companies:int, pending_companies:int} */
    public function counts(): array
    {
        return [
            'interns' => User::ofRole(Role::Intern)->active()->count(),
            'advisers' => User::ofRole(Role::Adviser)->active()->count(),
            'companies' => Company::registered()->approved()->count(),
            'pending_companies' => Company::registered()->pending()->count(),
        ];
    }

    /** @return array{placed:int, unplaced:int} */
    public function placementSplit(): array
    {
        $interns = User::ofRole(Role::Intern)->active();
        $placed = (clone $interns)->whereHas('activePlacement')->count();

        return ['placed' => $placed, 'unplaced' => (clone $interns)->count() - $placed];
    }

    /** @return array{with_class:int, without_class:int} */
    public function sectioningSplit(): array
    {
        $interns = User::ofRole(Role::Intern)->active();
        $withClass = (clone $interns)->whereHas('internProfile', fn ($q) => $q->whereNotNull('class_section_id'))->count();

        return ['with_class' => $withClass, 'without_class' => (clone $interns)->count() - $withClass];
    }

    /** @return array<string, array{active:int, disabled:int}> */
    public function accountStatusByRole(): array
    {
        $rows = User::query()
            ->selectRaw('role, status, count(*) as total')
            ->whereIn('role', [Role::Intern->value, Role::Adviser->value, Role::Company->value])
            ->groupBy('role', 'status')
            ->get();

        $result = [];
        foreach ([Role::Intern, Role::Adviser, Role::Company] as $role) {
            $result[$role->label()] = [
                'active' => (int) $rows->first(fn ($r) => $r->role === $role && $r->status === AccountStatus::Active)?->total,
                'disabled' => (int) $rows->first(fn ($r) => $r->role === $role && $r->status === AccountStatus::Disabled)?->total,
            ];
        }

        return $result;
    }
}
