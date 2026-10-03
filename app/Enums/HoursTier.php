<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum HoursTier: string
{
    use HasLabel;

    case Low = 'low';
    case Mid = 'mid';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Below certificate threshold',
            self::Mid => 'Certificate eligible',
            self::Complete => 'Complete',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Low => 'rose',
            self::Mid => 'amber',
            self::Complete => 'green',
        };
    }
}
