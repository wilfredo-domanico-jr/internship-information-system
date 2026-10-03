<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class JoinCodeGenerator
{
    /** Uppercase letters and digits without 0/O/1/I to keep codes easy to read aloud. */
    public const DEFAULT_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly string $alphabet = self::DEFAULT_ALPHABET,
        private readonly int $maxAttempts = 25,
    ) {}

    public function generate(string $table, string $column, int $length = 8): string
    {
        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            $code = $this->random($length);

            if (! DB::table($table)->where($column, $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException("Could not generate a unique code for {$table}.{$column}.");
    }

    private function random(int $length): string
    {
        $max = strlen($this->alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $this->alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
