<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum DocumentRequestStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Declined = 'declined';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Fulfilled => 'green',
            self::Declined => 'rose',
        };
    }
}
