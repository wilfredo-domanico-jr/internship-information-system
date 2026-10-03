<?php

use App\Actions\RegisterCompany;
use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\CompanyRegistered;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

function companyData(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'TechNova Solutions Inc.', 'company_type' => 'IT Services',
        'first_name' => 'Marco', 'last_name' => 'Villanueva', 'email' => 'hr@technova.example',
        'phone' => null, 'address' => 'Pasig City', 'website' => null, 'about' => null,
        'password' => 'Secret-Pass-123',
    ], $overrides);
}

function registerCompanyWithFiles(array $overrides = []): User
{
    return app(RegisterCompany::class)(
        companyData($overrides),
        UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('moa.pdf', 100, 'application/pdf'),
    );
}

it('creates the company user, pending company and stores both documents', function () {
    $user = registerCompanyWithFiles();
    $company = $user->company;

    expect($user->role)->toBe(Role::Company)
        ->and($user->member_no)->toStartWith('CMP-')
        ->and($company->approval_status)->toBe(ApprovalStatus::Pending)
        ->and($company->company_code)->toHaveLength(8)
        ->and($company->permit_path)->toBe("companies/{$company->id}/permit.pdf")
        ->and($company->moa_path)->toBe("companies/{$company->id}/moa.pdf");

    Storage::disk('local')->assertExists($company->permit_path);
    Storage::disk('local')->assertExists($company->moa_path);
});

it('notifies active admins only', function () {
    $active = User::factory()->admin()->create();
    $disabled = User::factory()->admin()->disabled()->create();

    registerCompanyWithFiles();

    Notification::assertSentTo($active, CompanyRegistered::class);
    Notification::assertNotSentTo($disabled, CompanyRegistered::class);
});

it('does not notify admins when registration fails', function () {
    User::factory()->admin()->create();
    User::factory()->create(['email' => 'hr@technova.example']);

    expect(fn () => registerCompanyWithFiles())->toThrow(QueryException::class);

    Notification::assertNothingSent();
});
