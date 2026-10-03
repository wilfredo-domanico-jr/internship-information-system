<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->company(),
            'type' => fake()->randomElement(['IT Services', 'BPO', 'Government', 'Manufacturing', 'Education', 'Finance']),
            'company_code' => Str::upper(Str::random(8)),
            'about' => fake()->paragraph(),
            'website' => fake()->url(),
            'address' => fake()->address(),
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
        ];
    }

    /** A company with a portal login and uploaded documents. */
    public function registered(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->state(['role' => Role::Company]),
            'permit_path' => 'companies/demo/permit.pdf',
            'moa_path' => 'companies/demo/moa.pdf',
        ]);
    }

    /** A COS partner company without a login. */
    public function partner(): static
    {
        return $this->state(['user_id' => null, 'approval_status' => ApprovalStatus::Approved]);
    }

    public function pending(): static
    {
        return $this->state(['approval_status' => ApprovalStatus::Pending, 'approved_at' => null, 'approved_by' => null]);
    }
}
