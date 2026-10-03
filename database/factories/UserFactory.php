<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Intern,
            // Closure runs after states are merged, so the prefix follows the final role.
            'member_no' => fn (array $attributes) => $this->memberNo($attributes['role']),
            'status' => AccountStatus::Active,
            'phone' => fake()->numerify('09#########'),
            'avatar_path' => null,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    private function memberNo(Role|string $role): string
    {
        $role = $role instanceof Role ? $role : Role::from($role);

        return sprintf('%s-%d-%05d', $role->memberPrefix(), now()->year, fake()->unique()->numberBetween(1, 99999));
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function adviser(): static
    {
        return $this->state(['role' => Role::Adviser]);
    }

    public function intern(): static
    {
        return $this->state(['role' => Role::Intern])
            ->afterCreating(function (User $user) {
                if (! $user->internProfile()->exists()) {
                    InternProfile::factory()->for($user)->create();
                }
            });
    }

    public function company(): static
    {
        return $this->state(['role' => Role::Company])
            ->afterCreating(function (User $user) {
                if (! $user->company()->exists()) {
                    Company::factory()->registered()->create(['user_id' => $user->id]);
                }
            });
    }

    public function disabled(): static
    {
        return $this->state(['status' => AccountStatus::Disabled]);
    }
}
