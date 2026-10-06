<?php

use App\Models\AnnouncementComment;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('seeds a coherent demo dataset', function () {
    Storage::fake('local');
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
        ->and(InternshipPosting::open()->count())->toBe(2)
        ->and(ClassSubmission::count())->toBe(3)
        ->and(ClassSubmission::where('is_late', true)->count())->toBe(1)
        ->and(ClassResource::count())->toBe(1)
        ->and(AnnouncementComment::count())->toBe(1)
        ->and(Application::count())->toBe(2)
        ->and(Interview::count())->toBe(1)
        ->and(DocumentRequest::count())->toBe(1)
        ->and(Certificate::count())->toBe(1);

    foreach (Dtr::all() as $dtr) {
        Storage::disk('local')->assertExists($dtr->file_path);
    }
    Storage::disk('local')->assertExists(Certificate::firstOrFail()->file_path);
    Storage::disk('local')->assertExists(Application::firstOrFail()->resume_path);

    $paths = ClassSubmission::pluck('file_path')->merge(ClassResource::pluck('file_path'));
    expect($paths)->toHaveCount(4)->and($paths->unique())->toHaveCount(4);
    $paths->each(fn (string $path) => Storage::disk('local')->assertExists($path));
});
