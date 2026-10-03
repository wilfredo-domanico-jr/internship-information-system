<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ApprovalStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'rose',
        };
    }
}
