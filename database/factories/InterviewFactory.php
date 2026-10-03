<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => Application::factory()->forInterview(),
            'title' => 'Initial Interview',
            'venue' => fake()->randomElement(['Google Meet', 'Zoom', 'On-site, 3F HR Office']),
            'link' => 'https://meet.google.com/abc-defg-hij',
            'scheduled_on' => now()->addDays(3)->toDateString(),
            'starts_at' => '10:00:00',
            'ends_at' => '10:30:00',
        ];
    }
}
