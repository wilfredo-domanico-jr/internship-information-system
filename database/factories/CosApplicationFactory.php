<?php

namespace Database\Factories;

use App\Enums\CosApplicationStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\CosApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CosApplication>
 */
class CosApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->partner(),
            'intern_id' => User::factory()->state(['role' => Role::Intern]),
            'acceptance_letter_path' => 'cos/demo/acceptance.pdf',
            'status' => CosApplicationStatus::Pending,
        ];
    }
}
