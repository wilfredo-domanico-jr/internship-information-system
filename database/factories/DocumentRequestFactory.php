<?php

namespace Database\Factories;

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentRequest;
use App\Models\Placement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory(),
            'control_no' => 'CTRL-'.fake()->unique()->numerify('######'),
            'document_name' => fake()->randomElement(['Certificate of Completion', 'Acceptance Letter', 'Evaluation Form']),
            'message' => fake()->sentence(),
            'status' => DocumentRequestStatus::Pending,
        ];
    }
}
