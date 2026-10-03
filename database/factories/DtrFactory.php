<?php

namespace Database\Factories;

use App\Enums\DtrStatus;
use App\Models\Dtr;
use App\Models\Placement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dtr>
 */
class DtrFactory extends Factory
{
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory(),
            'file_path' => 'dtrs/demo/dtr.pdf',
            'period_from' => now()->subWeek()->startOfWeek()->toDateString(),
            'period_to' => now()->subWeek()->endOfWeek()->toDateString(),
            'hours' => 40,
            'absences' => 0,
            'status' => DtrStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => DtrStatus::Approved, 'reviewed_at' => now()]);
    }

    public function disapproved(): static
    {
        return $this->state(['status' => DtrStatus::Disapproved, 'reviewed_at' => now(), 'reviewer_note' => 'Hours do not match the attached sheet.']);
    }
}
