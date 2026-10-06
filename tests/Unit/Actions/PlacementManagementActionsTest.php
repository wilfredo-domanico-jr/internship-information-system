<?php

use App\Actions\AssignDepartment;
use App\Actions\RemoveIntern;
use App\Exceptions\DomainRuleViolation;
use App\Models\Department;
use App\Models\Placement;
use App\Notifications\InternRemoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('assigns and clears a department', function () {
    $placement = Placement::factory()->create();
    $department = Department::factory()->create(['name' => 'Web Team']);

    app(AssignDepartment::class)($placement, $department->id);
    expect($placement->refresh()->department->name)->toBe('Web Team');

    app(AssignDepartment::class)($placement, null);
    expect($placement->refresh()->department_id)->toBeNull();
});

it('removes an active intern, keeps their hours and tells them; refuses ended placements', function () {
    Notification::fake();
    $placement = Placement::factory()->create(['hours_rendered' => 80]);

    app(RemoveIntern::class)($placement);

    expect($placement->refresh()->ended_at->toDateString())->toBe(today()->toDateString())->and($placement->hours_rendered)->toBe(80)
        ->and($placement->intern->hasActivePlacement())->toBeFalse();
    Notification::assertSentTo($placement->intern, InternRemoved::class);

    expect(fn () => app(RemoveIntern::class)($placement))->toThrow(DomainRuleViolation::class);
});
