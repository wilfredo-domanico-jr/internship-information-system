<?php

namespace App\Support;

class ImportResult
{
    /** @param  array<int, array<int, string>>  $errors  row number => messages */
    public function __construct(public int $created = 0, public array $errors = []) {}

    public function addError(int $row, string $message): void
    {
        $this->errors[$row][] = $message;
    }

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<int, string> flattened "Row N: message" lines, sorted by row */
    public function messages(): array
    {
        ksort($this->errors);
        $lines = [];
        foreach ($this->errors as $row => $messages) {
            foreach ($messages as $message) {
                $lines[] = "Row {$row}: {$message}";
            }
        }

        return $lines;
    }

    /** @return array{created:int, errors:array<int,string>} session-safe shape */
    public function toArray(): array
    {
        return ['created' => $this->created, 'errors' => $this->messages()];
    }
}
