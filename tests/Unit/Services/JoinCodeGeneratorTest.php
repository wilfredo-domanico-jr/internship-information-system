<?php

use App\Models\ClassSection;
use App\Services\JoinCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates unambiguous uppercase codes of the requested length', function () {
    $generator = app(JoinCodeGenerator::class);
    $codes = collect(range(1, 50))->map(fn () => $generator->generate('class_sections', 'join_code'));

    expect($codes->unique())->toHaveCount(50);
    $codes->each(fn ($code) => expect($code)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/'));
    expect($generator->generate('companies', 'company_code', 6))->toHaveLength(6);
});

it('never returns a code that already exists', function () {
    ClassSection::factory()->create(['join_code' => 'AAAAAAAA']);
    $generator = new JoinCodeGenerator(alphabet: 'A');

    expect(fn () => $generator->generate('class_sections', 'join_code', 8))
        ->toThrow(RuntimeException::class);
});
