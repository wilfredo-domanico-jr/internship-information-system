<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Models\User;

class ReactivateUser
{
    public function __invoke(User $user): void
    {
        $user->update(['status' => AccountStatus::Active]);
    }
}
