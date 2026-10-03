<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Placement>
 */
class PlacementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'intern_id' => User::factory()->state(['role' => Role::Intern]),
            'company_id' => Company::factory()->registered(),
            'department_id' => null,
            'started_at' => now()->subMonths(2)->toDateString(),
            'ended_at' => null,
            'hours_rendered' => 0,
            'absences' => 0,
        ];
    }

    public function ended(): static
    {
        return $this->state([
            'started_at' => now()->subMonths(8)->toDateString(),
            'ended_at' => now()->subMonths(5)->toDateString(),
        ]);
    }
}
