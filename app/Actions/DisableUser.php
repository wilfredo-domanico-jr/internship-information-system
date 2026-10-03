<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;

class DisableUser
{
    public function __invoke(User $user, User $actor): void
    {
        if ($actor->is($user)) {
            throw new DomainRuleViolation('You cannot disable your own account.');
        }

        if ($user->isAdmin()) {
            throw new DomainRuleViolation('Administrator accounts cannot be disabled from here.');
        }

        $user->update(['status' => AccountStatus::Disabled]);
    }
}
