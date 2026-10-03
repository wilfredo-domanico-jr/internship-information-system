<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_section_id' => ClassSection::factory(),
            'author_id' => User::factory()->state(['role' => Role::Adviser]),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }
}
