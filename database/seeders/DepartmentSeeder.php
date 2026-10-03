<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Information Technology', 'Human Resources', 'Administration', 'Finance',
            'Marketing', 'Operations', 'Security', 'Engineering', 'Customer Support',
        ];

        foreach ($names as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
