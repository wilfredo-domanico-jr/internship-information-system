<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum DtrStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Disapproved = 'disapproved';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Disapproved => 'rose',
        };
    }
}
