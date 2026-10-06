<?php

use App\Actions\CreatePosting;
use App\Actions\DeletePosting;
use App\Actions\TogglePostingStatus;
use App\Actions\UpdatePosting;
use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Enums\PostingStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\Company;
use App\Models\InternshipPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$payload = fn () => [
    'title' => 'QA Intern', 'city' => 'Pasig', 'description' => 'Test web apps.', 'responsibilities' => 'Write test cases.',
    'closing_date' => now()->addMonth()->toDateString(), 'required_hours' => 486, 'vacancies' => 2,
    'contact_name' => 'Ana Cruz', 'contact_position' => 'HR Officer', 'contact_phone' => '09170000000',
];

it('creates an open posting for the company and updates it', function () use ($payload) {
    $company = Company::factory()->registered()->create();

    $posting = app(CreatePosting::class)($company, $payload());
    expect($posting->company_id)->toBe($company->id)->and($posting->status)->toBe(PostingStatus::Open)
        ->and($posting->vacancies)->toBe(2)->and($posting->closing_date->toDateString())->toBe(now()->addMonth()->toDateString());

    app(UpdatePosting::class)($posting, [...$payload(), 'title' => 'Senior QA Intern', 'closing_date' => null, 'required_hours' => null]);
    expect($posting->refresh()->title)->toBe('Senior QA Intern')->and($posting->closing_date)->toBeNull()->and($posting->required_hours)->toBeNull();
});

it('toggles between open and closed and knows when it accepts applications', function () {
    $posting = InternshipPosting::factory()->create(['closing_date' => now()->addDay()->toDateString()]);
    expect($posting->isAcceptingApplications())->toBeTrue();

    app(TogglePostingStatus::class)($posting);
    expect($posting->refresh()->status)->toBe(PostingStatus::Closed)->and($posting->isAcceptingApplications())->toBeFalse();

    app(TogglePostingStatus::class)($posting);
    expect($posting->refresh()->status)->toBe(PostingStatus::Open);

    $posting->update(['closing_date' => now()->subDay()->toDateString()]);
    expect($posting->refresh()->isAcceptingApplications())->toBeFalse()
        ->and(InternshipPosting::accepting()->count())->toBe(0);

    $posting->update(['closing_date' => null]);
    expect($posting->refresh()->isAcceptingApplications())->toBeTrue()->and(InternshipPosting::accepting()->count())->toBe(1);
});

it('deletes a posting without applications but refuses one that has any', function () {
    $empty = InternshipPosting::factory()->create();
    $used = InternshipPosting::factory()->create();
    Application::factory()->for($used, 'posting')->create();

    app(DeletePosting::class)($empty);
    expect(InternshipPosting::whereKey($empty->id)->exists())->toBeFalse();

    expect(fn () => app(DeletePosting::class)($used))->toThrow(DomainRuleViolation::class);
    expect(InternshipPosting::whereKey($used->id)->exists())->toBeTrue();
});

it('does not accept applications for postings of rejected or disabled companies', function () {
    $rejected = InternshipPosting::factory()->for(Company::factory()->registered()->create(['approval_status' => ApprovalStatus::Rejected]))->create();
    $disabled = InternshipPosting::factory()->for(Company::factory()->registered()->create())->create();
    $disabled->company->user->update(['status' => AccountStatus::Disabled]);
    $ok = InternshipPosting::factory()->for(Company::factory()->registered()->create())->create();

    expect(InternshipPosting::accepting()->pluck('id')->all())->toBe([$ok->id])
        ->and($rejected->fresh()->isAcceptingApplications())->toBeFalse()->and($disabled->fresh()->isAcceptingApplications())->toBeFalse();
});
