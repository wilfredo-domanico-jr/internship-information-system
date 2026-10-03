<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PostingStatus: string
{
    use HasLabel;

    case Open = 'open';
    case Closed = 'closed';

    public function badgeColor(): string
    {
        return $this === self::Open ? 'green' : 'gray';
    }
}
