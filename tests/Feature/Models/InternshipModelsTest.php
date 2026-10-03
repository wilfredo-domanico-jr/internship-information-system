<?php

use App\Enums\ApplicationStatus;
use App\Enums\DtrStatus;
use App\Enums\PostingStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('resolves an intern\'s active placement', function () {
    $intern = User::factory()->intern()->create();
    Placement::factory()->for($intern, 'intern')->ended()->create();
    $current = Placement::factory()->for($intern, 'intern')->create();

    expect($intern->placements)->toHaveCount(2)
        ->and($intern->activePlacement->is($current))->toBeTrue()
        ->and($current->isActive())->toBeTrue()
        ->and($current->company->activePlacements)->toHaveCount(1);
});

it('prevents an intern from applying twice to the same posting', function () {
    $application = Application::factory()->create();

    Application::factory()->create([
        'internship_posting_id' => $application->internship_posting_id,
        'intern_id' => $application->intern_id,
    ]);
})->throws(QueryException::class);

it('casts statuses and links DTRs to placements', function () {
    $dtr = Dtr::factory()->create(['hours' => 40]);

    expect($dtr->status)->toBe(DtrStatus::Pending)
        ->and($dtr->placement->dtrs)->toHaveCount(1)
        ->and($dtr->placement->intern->isIntern())->toBeTrue();
});

it('lists open postings per company with their applications', function () {
    $company = Company::factory()->registered()->create();
    $open = InternshipPosting::factory()->for($company)->create();
    InternshipPosting::factory()->for($company)->closed()->create();
    Application::factory()->for($open, 'posting')->count(2)->create();

    expect($company->postings)->toHaveCount(2)
        ->and(InternshipPosting::open()->count())->toBe(1)
        ->and($open->status)->toBe(PostingStatus::Open)
        ->and($open->applications)->toHaveCount(2)
        ->and($open->applications->first()->status)->toBe(ApplicationStatus::Pending);
});

it('stores notifications in the database', function () {
    expect(Schema::hasTable('notifications'))->toBeTrue();
});

it('refuses to delete a company that has placements', function () {
    $placement = Placement::factory()->create();

    $placement->company->delete();
})->throws(QueryException::class);

it('refuses to delete an intern that has placements', function () {
    $placement = Placement::factory()->create();

    $placement->intern->delete();
})->throws(QueryException::class);
