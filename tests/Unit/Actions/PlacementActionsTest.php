<?php

use App\Actions\LeaveCompany;
use App\Actions\PlaceIntern;
use App\Enums\AccountStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\Company;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternJoinedCompany;
use App\Notifications\InternLeftCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->company = Company::factory()->registered()->create(['company_code' => 'TECHNOVA']);
    $this->intern = User::factory()->intern()->create();
    $this->posting = InternshipPosting::factory()->for($this->company)->create();
});

it('places an intern who has an accepted application with the company and tells the company', function () {
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create();

    $placement = app(PlaceIntern::class)($this->intern, ' technova ');

    expect($placement->company_id)->toBe($this->company->id)->and($placement->intern_id)->toBe($this->intern->id)
        ->and($placement->started_at->toDateString())->toBe(today()->toDateString())->and($placement->ended_at)->toBeNull()
        ->and($placement->hours_rendered)->toBe(0)->and($this->intern->hasActivePlacement())->toBeTrue();
    Notification::assertSentTo($this->company->user, InternJoinedCompany::class);
});

it('refuses unknown, unapproved or partner codes, missing acceptance, and a second placement', function () {
    expect(fn () => app(PlaceIntern::class)($this->intern, 'NOPE0000'))->toThrow(DomainRuleViolation::class, 'No company');

    $pending = Company::factory()->registered()->pending()->create(['company_code' => 'PENDING1']);
    Application::factory()->for(InternshipPosting::factory()->for($pending), 'posting')->for($this->intern, 'intern')->accepted()->create();
    expect(fn () => app(PlaceIntern::class)($this->intern, 'PENDING1'))->toThrow(DomainRuleViolation::class, 'No company');

    Company::factory()->partner()->create(['company_code' => 'PARTNER1']);
    expect(fn () => app(PlaceIntern::class)($this->intern, 'PARTNER1'))->toThrow(DomainRuleViolation::class, 'No company');

    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->forInterview()->create();
    expect(fn () => app(PlaceIntern::class)($this->intern, 'TECHNOVA'))->toThrow(DomainRuleViolation::class, 'accepted application');

    Application::where('intern_id', $this->intern->id)->update(['status' => 'accepted']);
    Placement::factory()->for($this->intern, 'intern')->create();
    expect(fn () => app(PlaceIntern::class)($this->intern, 'TECHNOVA'))->toThrow(DomainRuleViolation::class, 'already placed');
    expect(Placement::where('company_id', $this->company->id)->count())->toBe(0);
});

it('leaving ends the active placement and tells the company', function () {
    $placement = Placement::factory()->for($this->intern, 'intern')->for($this->company)->create(['hours_rendered' => 120]);

    $ended = app(LeaveCompany::class)($this->intern);

    expect($ended->is($placement))->toBeTrue()->and($ended->ended_at->toDateString())->toBe(today()->toDateString())
        ->and($ended->hours_rendered)->toBe(120)->and($this->intern->hasActivePlacement())->toBeFalse();
    Notification::assertSentTo($this->company->user, InternLeftCompany::class);

    expect(fn () => app(LeaveCompany::class)($this->intern))->toThrow(DomainRuleViolation::class);
});

it('refuses a code whose company user is disabled', function () {
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create();
    $this->company->user->update(['status' => AccountStatus::Disabled]);

    expect(fn () => app(PlaceIntern::class)($this->intern, 'TECHNOVA'))->toThrow(DomainRuleViolation::class, 'No company');
});

it('refuses a second placement on a repeated submit', function () {
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create();

    app(PlaceIntern::class)($this->intern, 'TECHNOVA');
    expect(fn () => app(PlaceIntern::class)($this->intern, 'TECHNOVA'))->toThrow(DomainRuleViolation::class, 'already placed');
    expect(Placement::where('intern_id', $this->intern->id)->count())->toBe(1);
});

it('needs a new acceptance to rejoin a company after leaving or being removed', function (string $how) {
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create(['decided_at' => now()->subDays(10)]);
    $placement = Placement::factory()->for($this->intern, 'intern')->for($this->company)->create(['started_at' => now()->subDays(8)->toDateString()]);
    $how === 'leave' ? app(LeaveCompany::class)($this->intern) : $placement->update(['ended_at' => today()]);

    expect(fn () => app(PlaceIntern::class)($this->intern, 'TECHNOVA'))->toThrow(DomainRuleViolation::class, 'new acceptance');

    Application::where('intern_id', $this->intern->id)->update(['decided_at' => now()->addDay()]);
    expect(app(PlaceIntern::class)($this->intern, 'TECHNOVA')->ended_at)->toBeNull()
        ->and(Placement::where('intern_id', $this->intern->id)->count())->toBe(2);
})->with(['leave', 'removed']);
