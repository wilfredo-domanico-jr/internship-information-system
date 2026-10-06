<?php

use App\Models\Application;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\Placement;
use App\Services\CompanyDashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts the company’s own pending work', function () {
    $company = Company::factory()->registered()->create();
    $posting = InternshipPosting::factory()->for($company)->create();
    InternshipPosting::factory()->for($company)->closed()->create();
    Application::factory()->for($posting, 'posting')->count(2)->create();
    $forInterview = Application::factory()->for($posting, 'posting')->forInterview()->create();
    Interview::factory()->for($forInterview)->create(['scheduled_on' => now()->addDay()->toDateString()]);
    $active = Placement::factory()->for($company)->create();
    Placement::factory()->for($company)->ended()->create();
    Dtr::factory()->for($active)->create();
    Dtr::factory()->for($active)->approved()->create();
    DocumentRequest::factory()->for($active)->create();
    Certificate::factory()->for($active)->create();
    Placement::factory()->create(); // another company

    expect(app(CompanyDashboardStats::class)->counts($company))->toBe([
        'active_interns' => 1, 'open_postings' => 1, 'pending_applicants' => 2, 'upcoming_interviews' => 1,
        'pending_dtrs' => 1, 'pending_requests' => 1, 'certificates' => 1,
    ]);
});

it('buckets active interns by hours rendered at the company', function () {
    $company = Company::factory()->registered()->create();
    foreach ([0, 250, 251, 300, 350, 401, 486] as $hours) {
        Placement::factory()->for($company)->create(['hours_rendered' => $hours]);
    }
    Placement::factory()->for($company)->ended()->create(['hours_rendered' => 486]);

    $buckets = app(CompanyDashboardStats::class)->hourBuckets($company);

    expect($buckets['labels'])->toBe(['0–250', '251–300', '301–400', '401+'])->and($buckets['data'])->toBe([2, 2, 1, 2]);
});
