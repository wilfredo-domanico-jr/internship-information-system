<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementComment>
 */
class AnnouncementCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'author_id' => User::factory()->state(['role' => Role::Intern]),
            'body' => fake()->sentence(),
        ];
    }
}
