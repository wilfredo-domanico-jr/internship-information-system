<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'internship_posting_id' => InternshipPosting::factory(),
            'intern_id' => User::factory()->state(['role' => Role::Intern]),
            'resume_path' => 'applications/demo/resume.pdf',
            'endorsement_path' => 'applications/demo/endorsement.pdf',
            'status' => ApplicationStatus::Pending,
        ];
    }

    public function forInterview(): static
    {
        return $this->state(['status' => ApplicationStatus::ForInterview]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => ApplicationStatus::Accepted, 'decided_at' => now()]);
    }

    public function declined(): static
    {
        return $this->state(['status' => ApplicationStatus::Declined, 'decided_at' => now(), 'decline_reason' => 'Position filled.']);
    }
}
