<?php

use App\Actions\CreatePartnerCompany;
use App\Actions\DeletePartnerCompany;
use App\Actions\UpdatePartnerCompany;
use App\Enums\ApprovalStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Company;
use App\Models\CosApplication;
use App\Models\Placement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('public'));

it('creates an approved partner company with a code and logo', function () {
    $company = app(CreatePartnerCompany::class)(['name' => 'City Hall ICT', 'type' => 'Government'], UploadedFile::fake()->image('logo.png'));

    expect($company->isPartner())->toBeTrue()
        ->and($company->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($company->company_code)->toHaveLength(8)
        ->and($company->logo_path)->toStartWith('partner-logos/');
    Storage::disk('public')->assertExists($company->logo_path);
});

it('updates fields and replaces the logo', function () {
    $company = app(CreatePartnerCompany::class)(['name' => 'Old'], UploadedFile::fake()->image('a.png'));
    $old = $company->logo_path;

    app(UpdatePartnerCompany::class)($company, ['name' => 'New Name', 'about' => 'Updated'], UploadedFile::fake()->image('b.png'));

    expect($company->refresh()->name)->toBe('New Name')->and($company->logo_path)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($company->logo_path);
});

it('deletes an unused partner company and its logo', function () {
    $company = app(CreatePartnerCompany::class)(['name' => 'Temp'], UploadedFile::fake()->image('a.png'));
    $logo = $company->logo_path;

    app(DeletePartnerCompany::class)($company);

    expect(Company::find($company->id))->toBeNull();
    Storage::disk('public')->assertMissing($logo);
});

it('refuses to delete a partner company with placements or COS applications', function () {
    $withPlacement = Company::factory()->partner()->create();
    Placement::factory()->for($withPlacement)->create();
    $withApplication = Company::factory()->partner()->create();
    CosApplication::factory()->for($withApplication)->create();

    expect(fn () => app(DeletePartnerCompany::class)($withPlacement))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DeletePartnerCompany::class)($withApplication))->toThrow(DomainRuleViolation::class);
    expect(Company::count())->toBe(2);
});
