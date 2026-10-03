<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

trait HasLabel
{
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
