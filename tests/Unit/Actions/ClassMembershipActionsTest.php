<?php

use App\Actions\ClaimClass;
use App\Actions\LeaveClass;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an adviser claim an unassigned active class by code, case-insensitively', function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->unassigned()->create(['join_code' => 'SBIT4C26']);

    $claimed = app(ClaimClass::class)(' sbit4c26 ', $adviser);

    expect($claimed->is($section))->toBeTrue()
        ->and($section->refresh()->adviser_id)->toBe($adviser->id)
        ->and(ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->whereNull('left_at')->count())->toBe(1);
});

it('refuses codes that are unknown, archived or already taken', function () {
    $adviser = User::factory()->adviser()->create();
    ClassSection::factory()->create(['join_code' => 'TAKEN001']);
    ClassSection::factory()->unassigned()->archived()->create(['join_code' => 'ARCHIVED']);
    $mine = ClassSection::factory()->for($adviser, 'adviser')->create(['join_code' => 'MINE0001']);

    expect(fn () => app(ClaimClass::class)('NOPE0000', $adviser))->toThrow(DomainRuleViolation::class, 'No active class');
    expect(fn () => app(ClaimClass::class)('ARCHIVED', $adviser))->toThrow(DomainRuleViolation::class, 'No active class');
    expect(fn () => app(ClaimClass::class)('TAKEN001', $adviser))->toThrow(DomainRuleViolation::class, 'already has an adviser');
    expect(fn () => app(ClaimClass::class)('MINE0001', $adviser))->toThrow(DomainRuleViolation::class, 'already advise');
    expect(ClassAdviserLog::count())->toBe(0);
});

it('leaving unassigns the adviser and closes the log, and the class can be claimed again', function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->unassigned()->create(['join_code' => 'SBIT4C26']);
    app(ClaimClass::class)('SBIT4C26', $adviser);

    app(LeaveClass::class)($section->refresh(), $adviser);

    $log = ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->firstOrFail();
    expect($section->refresh()->adviser_id)->toBeNull()->and($log->left_at)->not->toBeNull();

    $next = User::factory()->adviser()->create();
    app(ClaimClass::class)('SBIT4C26', $next);
    expect($section->refresh()->adviser_id)->toBe($next->id)->and(ClassAdviserLog::where('class_section_id', $section->id)->count())->toBe(2);
});

it('records history even when a class had no open log row', function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->for($adviser, 'adviser')->create();

    app(LeaveClass::class)($section, $adviser);

    $log = ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->firstOrFail();
    expect($log->joined_at->equalTo($section->created_at))->toBeTrue()->and($log->left_at)->not->toBeNull();
});

it('refuses to leave a class you do not advise', function () {
    $section = ClassSection::factory()->create();

    expect(fn () => app(LeaveClass::class)($section, User::factory()->adviser()->create()))->toThrow(DomainRuleViolation::class);
    expect($section->refresh()->adviser_id)->not->toBeNull();
});
