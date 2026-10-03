<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternProfile>
 */
class InternProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => Role::Intern]),
            'student_number' => fake()->unique()->numerify('##-####'),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'birthdate' => fake()->dateTimeBetween('-25 years', '-19 years')->format('Y-m-d'),
            'present_address' => fake()->address(),
            'permanent_address' => fake()->address(),
            'about' => null,
            'school_year' => '2025-2026',
            'total_hours' => 0,
            'total_absences' => 0,
        ];
    }
}
