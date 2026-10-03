<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassResource>
 */
class ClassResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_section_id' => ClassSection::factory(),
            'uploader_id' => User::factory()->state(['role' => Role::Adviser]),
            'title' => fake()->words(3, true),
            'file_path' => 'classroom/demo/resource.pdf',
        ];
    }
}
