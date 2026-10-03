<?php

namespace Database\Factories;

use App\Models\ClassFolder;
use App\Models\ClassSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassFolder>
 */
class ClassFolderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_section_id' => ClassSection::factory(),
            'name' => fake()->randomElement(['Resume', 'Endorsement Letter', 'Weekly Report 1', 'MOA Copy']),
            'is_locked' => false,
        ];
    }

    public function locked(): static
    {
        return $this->state(['is_locked' => true]);
    }
}
