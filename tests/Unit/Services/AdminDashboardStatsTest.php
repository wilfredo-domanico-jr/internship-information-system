<?php

use App\Enums\ApprovalStatus;
use App\Models\ClassSection;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;
use App\Services\AdminDashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stats = new AdminDashboardStats;
    $section = ClassSection::factory()->create(); // creates one adviser
    $placed = User::factory()->intern()->create();
    // Partner company so no extra company *user* is created by the placement factory.
    Placement::factory()->for($placed, 'intern')->for(Company::factory()->partner()->create())->create();
    $placed->internProfile()->update(['class_section_id' => $section->id]);
    User::factory()->intern()->create();                 // unplaced, no class
    User::factory()->intern()->disabled()->create();     // excluded from active counts
    User::factory()->company()->create();                // approved registered
    $pending = User::factory()->company()->create();
    $pending->company->update(['approval_status' => ApprovalStatus::Pending]);
});

it('counts the headline numbers', function () {
    // partner companies (no login) are not counted as verified companies
    expect($this->stats->counts())->toBe(['interns' => 2, 'advisers' => 1, 'companies' => 1, 'pending_companies' => 1]);
});

it('splits active interns by placement and by class', function () {
    expect($this->stats->placementSplit())->toBe(['placed' => 1, 'unplaced' => 1])
        ->and($this->stats->sectioningSplit())->toBe(['with_class' => 1, 'without_class' => 1]);
});

it('splits accounts by status per role', function () {
    expect($this->stats->accountStatusByRole())->toBe([
        'Intern' => ['active' => 2, 'disabled' => 1],
        'Adviser' => ['active' => 1, 'disabled' => 0],
        'Company' => ['active' => 2, 'disabled' => 0],
    ]);
});
