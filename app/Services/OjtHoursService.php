<?php

namespace App\Services;

use App\Enums\HoursTier;

class OjtHoursService
{
    public function required(): int
    {
        return (int) config('wiis.hours.required');
    }

    public function certificateMinimum(): int
    {
        return (int) config('wiis.hours.certificate_min');
    }

    public function isComplete(int $hours): bool
    {
        return $hours >= $this->required();
    }

    public function isCertificateEligible(int $hours): bool
    {
        return $hours >= $this->certificateMinimum();
    }

    public function progressPercent(int $hours): int
    {
        $required = max(1, $this->required());

        return (int) min(100, floor($hours / $required * 100));
    }

    public function remaining(int $hours): int
    {
        return max(0, $this->required() - $hours);
    }

    public function tier(int $hours): HoursTier
    {
        return match (true) {
            $this->isComplete($hours) => HoursTier::Complete,
            $this->isCertificateEligible($hours) => HoursTier::Mid,
            default => HoursTier::Low,
        };
    }

    public function bucketLabel(int $hours): string
    {
        foreach ($this->buckets() as [$min, $max, $label]) {
            if ($hours >= $min && ($max === null || $hours <= $max)) {
                return $label;
            }
        }

        return $this->buckets()[array_key_last($this->buckets())][2];
    }

    /** @return array<int, string> */
    public function bucketLabels(): array
    {
        return array_map(fn (array $bucket) => $bucket[2], $this->buckets());
    }

    /** @return array<int, array{0:int,1:int|null,2:string}> */
    private function buckets(): array
    {
        return config('wiis.hours.buckets');
    }
}
