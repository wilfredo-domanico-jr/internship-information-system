<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Information Technology', 'Human Resources', 'Administration', 'Finance',
                'Marketing', 'Operations', 'Security', 'Engineering', 'Customer Support',
            ]),
        ];
    }
}
