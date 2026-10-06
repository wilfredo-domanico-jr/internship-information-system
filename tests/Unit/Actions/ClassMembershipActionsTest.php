<?php

use App\Actions\ClaimClass;
use App\Actions\JoinClass;
use App\Actions\LeaveClass;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\InternJoinedClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

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

it('enrols an intern by join code and tells the adviser', function () {
    Notification::fake();
    $section = ClassSection::factory()->create(['join_code' => 'SBIT4C26', 'school_year' => '2026-2027']);
    $intern = User::factory()->intern()->create();

    $joined = app(JoinClass::class)($intern, 'sbit4c26');

    expect($joined->is($section))->toBeTrue()
        ->and($intern->internProfile->refresh()->class_section_id)->toBe($section->id)
        ->and($intern->internProfile->school_year)->toBe('2026-2027');
    Notification::assertSentTo($section->adviser, InternJoinedClass::class, fn (InternJoinedClass $n) => $n->toArray($section->adviser)['url'] === route('adviser.classes.people', $section));
});

it('refuses a second active class, unknown codes and archived classes', function () {
    $current = ClassSection::factory()->create(['join_code' => 'FIRST001']);
    $other = ClassSection::factory()->create(['join_code' => 'OTHER001']);
    ClassSection::factory()->archived()->create(['join_code' => 'ARCHIVED']);
    $intern = User::factory()->intern()->create();
    $intern->internProfile->update(['class_section_id' => $current->id]);

    expect(fn () => app(JoinClass::class)($intern, 'OTHER001'))->toThrow(DomainRuleViolation::class, 'already enrolled');
    expect($intern->internProfile->refresh()->class_section_id)->toBe($current->id);

    $free = User::factory()->intern()->create();
    expect(fn () => app(JoinClass::class)($free, 'NOPE0000'))->toThrow(DomainRuleViolation::class, 'No active class');
    expect(fn () => app(JoinClass::class)($free, 'ARCHIVED'))->toThrow(DomainRuleViolation::class, 'No active class');
});

it('lets an intern whose class was archived join a new one', function () {
    $old = ClassSection::factory()->archived()->create();
    $new = ClassSection::factory()->create(['join_code' => 'NEWCLASS']);
    $intern = User::factory()->intern()->create();
    $intern->internProfile->update(['class_section_id' => $old->id]);

    app(JoinClass::class)($intern, 'NEWCLASS');

    expect($intern->internProfile->refresh()->class_section_id)->toBe($new->id);
});

it('lets only one of two advisers claim the same class', function () {
    $first = User::factory()->adviser()->create();
    $second = User::factory()->adviser()->create();
    $section = ClassSection::factory()->unassigned()->create(['join_code' => 'RACE0001']);

    app(ClaimClass::class)('RACE0001', $first);
    expect(fn () => app(ClaimClass::class)('RACE0001', $second))->toThrow(DomainRuleViolation::class, 'already has an adviser');

    expect($section->refresh()->adviser_id)->toBe($first->id)
        ->and(ClassAdviserLog::where('class_section_id', $section->id)->whereNull('left_at')->count())->toBe(1);
});
