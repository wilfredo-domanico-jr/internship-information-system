<?php

namespace Database\Factories;

use App\Enums\ClassStatus;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClassSection>
 */
class ClassSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'adviser_id' => User::factory()->state(['role' => Role::Adviser]),
            'course_code' => fake()->randomElement(['CC101', 'IT401', 'CS402', 'IS403']),
            'subject' => fake()->randomElement(['Practicum', 'On-the-Job Training', 'Internship 1']),
            'section' => 'SBIT-4'.fake()->randomLetter(),
            'day' => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
            'starts_at' => '08:00:00',
            'ends_at' => '12:00:00',
            'school_year' => '2025-2026',
            'join_code' => Str::upper(Str::random(8)),
            'status' => ClassStatus::Active,
        ];
    }

    public function unassigned(): static
    {
        return $this->state(['adviser_id' => null]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ClassStatus::Archived]);
    }
}
