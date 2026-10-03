<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassAdviserLog>
 */
class ClassAdviserLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_section_id' => ClassSection::factory(),
            'adviser_id' => User::factory()->state(['role' => Role::Adviser]),
            'joined_at' => now()->subMonths(2),
            'left_at' => null,
        ];
    }
}
