<?php

namespace App\Services;

use App\Models\DocumentRequest;

/** Control numbers like DR-2026-00001: current year and a zero-padded sequence that skips collisions. */
class ControlNumberGenerator
{
    public function generate(): string
    {
        $year = now()->year;
        $sequence = DocumentRequest::query()->where('control_no', 'like', "DR-{$year}-%")->count();

        do {
            $sequence++;
            $candidate = sprintf('DR-%d-%05d', $year, $sequence);
        } while (DocumentRequest::query()->where('control_no', $candidate)->exists());

        return $candidate;
    }
}
