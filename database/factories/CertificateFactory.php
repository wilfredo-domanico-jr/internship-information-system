<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Placement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory(),
            'file_path' => 'certificates/demo/certificate.pdf',
            'hours_at_issue' => config('wiis.hours.required'),
            'issued_at' => now(),
        ];
    }
}
