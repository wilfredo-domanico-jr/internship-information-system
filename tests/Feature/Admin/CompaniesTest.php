<?php

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists registered companies with contact, status and intern count', function () {
    $companyUser = User::factory()->company()->create(['first_name' => 'Marco', 'last_name' => 'Villanueva']);
    $companyUser->company->update(['name' => 'TechNova Solutions', 'company_code' => 'TECHNOVA']);
    Placement::factory()->for($companyUser->company)->count(2)->create();
    Company::factory()->partner()->create(['name' => 'Partner Only']);

    $this->actingAs($this->admin)->get(route('admin.companies.index'))
        ->assertOk()->assertSee('TechNova Solutions')->assertSee('Marco Villanueva')->assertSee('TECHNOVA')->assertSee('2')
        ->assertDontSee('Partner Only');
});

it('filters by approval status and searches by name or code', function () {
    $approved = User::factory()->company()->create();
    $approved->company->update(['name' => 'Approved Corp', 'company_code' => 'APPR0001']);
    $pending = User::factory()->company()->create();
    $pending->company->update(['name' => 'Pending Inc', 'approval_status' => ApprovalStatus::Pending]);

    $this->actingAs($this->admin)->get(route('admin.companies.index', ['approval' => 'pending']))->assertSee('Pending Inc')->assertDontSee('Approved Corp');
    $this->actingAs($this->admin)->get(route('admin.companies.index', ['q' => 'appr0001']))->assertSee('Approved Corp')->assertDontSee('Pending Inc');
});

it('shows a company with documents, contact and active interns', function () {
    $companyUser = User::factory()->company()->create(['first_name' => 'Marco']);
    $company = $companyUser->company;
    $company->update(['name' => 'TechNova Solutions']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    Placement::factory()->for($intern, 'intern')->for($company)->create(['hours_rendered' => 300]);

    $this->actingAs($this->admin)->get(route('admin.companies.show', $company))
        ->assertOk()->assertSee('TechNova Solutions')->assertSee('Marco')->assertSee('Maria Santos')->assertSee('300')
        ->assertSee(route('files.show', ['company-permit', $company]))
        ->assertSee(route('files.show', ['company-moa', $company]));
});

it('returns 404 for partner companies on the registered-company page', function () {
    $partner = Company::factory()->partner()->create();

    $this->actingAs($this->admin)->get(route('admin.companies.show', $partner))->assertNotFound();
});
