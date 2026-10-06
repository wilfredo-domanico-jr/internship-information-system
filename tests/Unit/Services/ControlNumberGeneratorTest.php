<?php

use App\Models\DocumentRequest;
use App\Services\ControlNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates year-prefixed sequential control numbers that skip collisions', function () {
    $generator = app(ControlNumberGenerator::class);
    $year = now()->year;

    expect($generator->generate())->toBe("DR-{$year}-00001");

    DocumentRequest::factory()->create(['control_no' => "DR-{$year}-00001"]);
    DocumentRequest::factory()->create(['control_no' => "DR-{$year}-00002"]);

    expect($generator->generate())->toBe("DR-{$year}-00003");
});
