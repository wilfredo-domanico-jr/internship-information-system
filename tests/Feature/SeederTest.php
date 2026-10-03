<?php

use App\Models\ClassSection;
use App\Models\Company;
use App\Models\Department;
use App\Models\InternshipPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('seeds a coherent demo dataset', function () {
    $this->seed();

    $intern = User::where('email', 'intern@wiis.test')->firstOrFail();
    $intern2 = User::where('email', 'intern2@wiis.test')->firstOrFail();
    $company = User::where('email', 'company@wiis.test')->firstOrFail()->company;

    expect(Department::count())->toBeGreaterThanOrEqual(5)
        ->and(User::where('email', 'admin@wiis.test')->exists())->toBeTrue()
        ->and(ClassSection::where('join_code', 'SBIT4C26')->exists())->toBeTrue()
        ->and($company->company_code)->toBe('TECHNOVA')
        ->and($company->isApproved())->toBeTrue()
        ->and(Company::pending()->count())->toBe(1)
        ->and(Company::partners()->count())->toBe(1)
        ->and($intern->internProfile->total_hours)->toBe(300)
        ->and($intern->activePlacement->hours_rendered)->toBe(300)
        ->and($intern->activePlacement->company_id)->toBe($company->id)
        ->and($intern->activePlacement->dtrs()->count())->toBe(4)
        ->and($intern2->activePlacement)->toBeNull()
        ->and($intern2->internProfile->classSection->join_code)->toBe('SBIT4C26')
        ->and(InternshipPosting::open()->count())->toBe(2);
});

it('can seed twice without unique violations', function () {
    $this->seed();

    // migrate:fresh runs VACUUM on SQLite, which cannot happen inside RefreshDatabase's transaction.
    DB::rollBack();

    $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
});
