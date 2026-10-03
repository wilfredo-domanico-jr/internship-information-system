<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ClassStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Archived = 'archived';

    public function badgeColor(): string
    {
        return $this === self::Active ? 'green' : 'gray';
    }
}
