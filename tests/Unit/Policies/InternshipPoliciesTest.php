<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentRequestStatus;
use App\Enums\DtrStatus;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->companyUser = User::factory()->company()->create();
    $this->company = $this->companyUser->company;
    $this->otherCompanyUser = User::factory()->company()->create();
    $this->intern = User::factory()->intern()->create();
    $this->otherIntern = User::factory()->intern()->create();
    $this->admin = User::factory()->admin()->create();
    $this->posting = InternshipPosting::factory()->for($this->company)->create();
    $this->application = Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->create();
    $this->placement = Placement::factory()->for($this->intern, 'intern')->for($this->company)->create();
});

it('knows who manages and who owns', function () {
    expect($this->company->isManagedBy($this->companyUser))->toBeTrue()
        ->and($this->company->isManagedBy($this->otherCompanyUser))->toBeFalse()
        ->and(Company::factory()->partner()->create()->isManagedBy($this->companyUser))->toBeFalse()
        ->and($this->placement->isInternOf($this->intern))->toBeTrue()
        ->and($this->placement->isInternOf($this->otherIntern))->toBeFalse()
        ->and($this->placement->isManagedBy($this->companyUser))->toBeTrue()
        ->and($this->application->isOwnedBy($this->intern))->toBeTrue()
        ->and($this->application->isManagedBy($this->companyUser))->toBeTrue()
        ->and($this->application->isManagedBy($this->otherCompanyUser))->toBeFalse()
        ->and($this->intern->hasActivePlacement())->toBeTrue()
        ->and($this->otherIntern->hasActivePlacement())->toBeFalse();
});

it('scopes postings to their company and lets interns view open ones only', function () {
    expect($this->companyUser->can('manage', $this->posting))->toBeTrue()
        ->and($this->otherCompanyUser->can('manage', $this->posting))->toBeFalse()
        ->and($this->companyUser->can('view', $this->posting))->toBeTrue()
        ->and($this->intern->can('view', $this->posting))->toBeTrue()
        ->and($this->admin->can('view', $this->posting))->toBeTrue()
        ->and($this->otherCompanyUser->can('view', $this->posting))->toBeFalse();

    $closed = InternshipPosting::factory()->for($this->company)->closed()->create();
    expect($this->intern->can('view', $closed))->toBeFalse()->and($this->companyUser->can('view', $closed))->toBeTrue();
});

it('scopes applications to the intern and the posting company', function () {
    expect($this->intern->can('view', $this->application))->toBeTrue()
        ->and($this->companyUser->can('view', $this->application))->toBeTrue()
        ->and($this->admin->can('view', $this->application))->toBeTrue()
        ->and($this->otherIntern->can('view', $this->application))->toBeFalse()
        ->and($this->otherCompanyUser->can('view', $this->application))->toBeFalse()
        ->and($this->companyUser->can('decide', $this->application))->toBeTrue()
        ->and($this->otherCompanyUser->can('decide', $this->application))->toBeFalse()
        ->and($this->intern->can('decide', $this->application))->toBeFalse()
        ->and($this->intern->can('cancel', $this->application))->toBeTrue()
        ->and($this->otherIntern->can('cancel', $this->application))->toBeFalse();

    $this->application->update(['status' => ApplicationStatus::Accepted]);
    expect($this->intern->can('cancel', $this->application->fresh()))->toBeFalse();
});

it('scopes placements, DTRs, document requests and certificates', function () {
    $dtr = Dtr::factory()->for($this->placement)->create();
    $request = DocumentRequest::factory()->for($this->placement)->create();
    $certificate = Certificate::factory()->for($this->placement)->create();

    expect($this->intern->can('view', $this->placement))->toBeTrue()
        ->and($this->companyUser->can('view', $this->placement))->toBeTrue()
        ->and($this->otherIntern->can('view', $this->placement))->toBeFalse()
        ->and($this->companyUser->can('manage', $this->placement))->toBeTrue()
        ->and($this->otherCompanyUser->can('manage', $this->placement))->toBeFalse()
        ->and($this->intern->can('manage', $this->placement))->toBeFalse()
        ->and($this->intern->can('leave', $this->placement))->toBeTrue()
        ->and($this->companyUser->can('leave', $this->placement))->toBeFalse()
        ->and($this->intern->can('view', $dtr))->toBeTrue()
        ->and($this->companyUser->can('review', $dtr))->toBeTrue()
        ->and($this->otherCompanyUser->can('review', $dtr))->toBeFalse()
        ->and($this->otherCompanyUser->can('view', $dtr))->toBeFalse()
        ->and($this->intern->can('delete', $dtr))->toBeTrue()
        ->and($this->intern->can('view', $request))->toBeTrue()
        ->and($this->companyUser->can('handle', $request))->toBeTrue()
        ->and($this->otherCompanyUser->can('handle', $request))->toBeFalse()
        ->and($this->intern->can('update', $request))->toBeTrue()
        ->and($this->intern->can('delete', $request))->toBeTrue()
        ->and($this->otherIntern->can('update', $request))->toBeFalse()
        ->and($this->intern->can('view', $certificate))->toBeTrue()
        ->and($this->companyUser->can('view', $certificate))->toBeTrue()
        ->and($this->admin->can('view', $certificate))->toBeTrue()
        ->and($this->otherIntern->can('view', $certificate))->toBeFalse();

    $dtr->update(['status' => DtrStatus::Approved]);
    $request->update(['status' => DocumentRequestStatus::Fulfilled]);
    expect($this->intern->can('delete', $dtr->fresh()))->toBeFalse()
        ->and($this->intern->can('update', $request->fresh()))->toBeFalse()
        ->and($this->intern->can('delete', $request->fresh()))->toBeFalse();

    $this->placement->update(['ended_at' => now()->toDateString()]);
    expect($this->intern->can('leave', $this->placement->fresh()))->toBeFalse();
});
