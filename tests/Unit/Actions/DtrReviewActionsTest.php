<?php

use App\Actions\ApproveDtr;
use App\Actions\DisapproveDtr;
use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DtrReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile()->update(['total_hours' => 100, 'total_absences' => 2]);
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create(['hours_rendered' => 60, 'absences' => 1]);
    $this->reviewer = $this->placement->company->user;
});

it('adds the hours and absences to the placement and the intern total exactly once', function () {
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 40, 'absences' => 1]);

    app(ApproveDtr::class)($dtr, $this->reviewer);

    expect($dtr->refresh()->status)->toBe(DtrStatus::Approved)->and($dtr->reviewer_id)->toBe($this->reviewer->id)->and($dtr->reviewed_at)->not->toBeNull()
        ->and($this->placement->refresh()->hours_rendered)->toBe(100)->and($this->placement->absences)->toBe(2)
        ->and($this->intern->internProfile->refresh()->total_hours)->toBe(140)->and($this->intern->internProfile->total_absences)->toBe(3);
    Notification::assertSentTo($this->intern, DtrReviewed::class);

    expect(fn () => app(ApproveDtr::class)($dtr, $this->reviewer))->toThrow(DomainRuleViolation::class);
    expect($this->placement->refresh()->hours_rendered)->toBe(100)->and($this->intern->internProfile->refresh()->total_hours)->toBe(140);
});

it('disapproving never touches hours and cannot be approved afterwards', function () {
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 40]);

    app(DisapproveDtr::class)($dtr, $this->reviewer, 'Hours do not match the log.');

    expect($dtr->refresh()->status)->toBe(DtrStatus::Disapproved)->and($dtr->reviewer_note)->toBe('Hours do not match the log.')
        ->and($this->placement->refresh()->hours_rendered)->toBe(60)->and($this->intern->internProfile->refresh()->total_hours)->toBe(100);
    Notification::assertSentTo($this->intern, DtrReviewed::class, fn (DtrReviewed $n) => str_contains($n->toArray($this->intern)['body'], 'Hours do not match'));

    expect(fn () => app(ApproveDtr::class)($dtr, $this->reviewer))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DisapproveDtr::class)($dtr, $this->reviewer, 'again'))->toThrow(DomainRuleViolation::class);
    expect($this->placement->refresh()->hours_rendered)->toBe(60);
});

it('refuses to disapprove an approved DTR and to disapprove without a note', function () {
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 10]);
    expect(fn () => app(DisapproveDtr::class)($dtr, $this->reviewer, '   '))->toThrow(DomainRuleViolation::class);

    app(ApproveDtr::class)($dtr, $this->reviewer);
    expect(fn () => app(DisapproveDtr::class)($dtr, $this->reviewer, 'late'))->toThrow(DomainRuleViolation::class);
    expect($this->placement->refresh()->hours_rendered)->toBe(70);
});

it('credits hours even after the placement ended', function () {
    $this->placement->update(['ended_at' => today()]);
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 8]);

    app(ApproveDtr::class)($dtr, $this->reviewer);

    expect($this->placement->refresh()->hours_rendered)->toBe(68)->and($this->intern->internProfile->refresh()->total_hours)->toBe(108);
});
