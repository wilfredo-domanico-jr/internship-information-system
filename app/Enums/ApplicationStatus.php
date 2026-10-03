<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ApplicationStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case ForInterview = 'for_interview';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::ForInterview => 'sky',
            self::Accepted => 'green',
            self::Declined => 'rose',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::ForInterview], true);
    }
}
