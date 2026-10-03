<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum CosApplicationStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Declined => 'rose',
        };
    }
}
