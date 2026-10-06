<?php

use App\Actions\CancelApplication;
use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cancels a pending or for-interview application and refuses decided ones', function () {
    $pending = Application::factory()->create();
    $forInterview = Application::factory()->forInterview()->create();
    $accepted = Application::factory()->accepted()->create();
    $declined = Application::factory()->declined()->create();

    app(CancelApplication::class)($pending);
    app(CancelApplication::class)($forInterview);
    expect($pending->refresh()->status)->toBe(ApplicationStatus::Cancelled)->and($pending->decided_at)->not->toBeNull()
        ->and($forInterview->refresh()->status)->toBe(ApplicationStatus::Cancelled);

    expect(fn () => app(CancelApplication::class)($accepted))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(CancelApplication::class)($declined))->toThrow(DomainRuleViolation::class);
    expect($accepted->refresh()->status)->toBe(ApplicationStatus::Accepted);
});
