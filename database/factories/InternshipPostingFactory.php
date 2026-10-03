<?php

namespace Database\Factories;

use App\Enums\PostingStatus;
use App\Models\Company;
use App\Models\InternshipPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipPosting>
 */
class InternshipPostingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->registered(),
            'title' => fake()->randomElement(['Junior Web Developer Intern', 'IT Support Intern', 'QA Intern', 'Data Entry Intern']),
            'city' => fake()->randomElement(['Quezon City', 'Makati', 'Pasig', 'Taguig', 'Manila']),
            'description' => fake()->paragraphs(2, true),
            'responsibilities' => fake()->paragraph(),
            'closing_date' => now()->addMonth()->toDateString(),
            'required_hours' => config('wiis.hours.required'),
            'vacancies' => fake()->numberBetween(1, 5),
            'contact_name' => fake()->name(),
            'contact_position' => 'HR Officer',
            'contact_phone' => fake()->numerify('09#########'),
            'status' => PostingStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => PostingStatus::Closed]);
    }
}
