<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Role: string
{
    use HasLabel;

    case Admin = 'admin';
    case Adviser = 'adviser';
    case Company = 'company';
    case Intern = 'intern';

    public function dashboardRoute(): string
    {
        return "{$this->value}.dashboard";
    }

    public function memberPrefix(): string
    {
        return match ($this) {
            self::Admin => 'ADM',
            self::Adviser => 'ADV',
            self::Company => 'CMP',
            self::Intern => 'INT',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Admin => 'rose',
            self::Adviser => 'sky',
            self::Company => 'teal',
            self::Intern => 'amber',
        };
    }
}
