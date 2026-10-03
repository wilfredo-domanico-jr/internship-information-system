<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;

class MemberNumberGenerator
{
    /**
     * Display codes like INT-2026-00001: prefix by role, current year, zero-padded sequence.
     * Sequence starts from the number of existing users of that role and skips collisions.
     */
    public function generate(Role $role): string
    {
        $year = now()->year;
        $sequence = User::query()->where('role', $role)->count();

        do {
            $sequence++;
            $candidate = sprintf('%s-%d-%05d', $role->memberPrefix(), $year, $sequence);
        } while (User::query()->where('member_no', $candidate)->exists());

        return $candidate;
    }
}
