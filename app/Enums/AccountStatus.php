<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AccountStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Disabled = 'disabled';

    public function badgeColor(): string
    {
        return $this === self::Active ? 'green' : 'gray';
    }
}
