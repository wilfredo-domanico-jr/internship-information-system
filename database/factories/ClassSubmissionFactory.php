<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\ClassFolder;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSubmission>
 */
class ClassSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_folder_id' => ClassFolder::factory(),
            'intern_id' => User::factory()->state(['role' => Role::Intern]),
            'title' => fake()->words(3, true),
            'file_path' => 'classroom/demo/submission.pdf',
            'status' => SubmissionStatus::Pending,
            'is_late' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => SubmissionStatus::Approved, 'reviewed_at' => now()]);
    }

    public function declined(): static
    {
        return $this->state(['status' => SubmissionStatus::Declined, 'reviewed_at' => now(), 'reviewer_note' => 'Please resubmit.']);
    }
}
