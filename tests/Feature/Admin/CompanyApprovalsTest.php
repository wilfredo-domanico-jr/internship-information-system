<?php

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyApproved;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->companyUser = User::factory()->company()->create();
    $this->company = tap($this->companyUser->company)->update(['name' => 'BlueOrbit Analytics', 'approval_status' => ApprovalStatus::Pending, 'approved_at' => null]);
});

it('lists only pending companies with their documents', function () {
    $approved = User::factory()->company()->create();
    $approved->company->update(['name' => 'Already Verified']);

    $this->actingAs($this->admin)->get(route('admin.companies.pending'))
        ->assertOk()->assertSee('BlueOrbit Analytics')->assertDontSee('Already Verified')
        ->assertSee(route('files.show', ['company-permit', $this->company]))
        ->assertSee(route('admin.companies.approve', $this->company));
});

it('approves from the UI and the company can then use its portal', function () {
    $this->actingAs($this->companyUser)->get(route('company.dashboard'))->assertRedirect(route('account.pending'));

    $this->actingAs($this->admin)->post(route('admin.companies.approve', $this->company))
        ->assertRedirect()->assertSessionHas('success');

    expect($this->company->refresh()->isApproved())->toBeTrue();
    Notification::assertSentTo($this->companyUser, CompanyApproved::class);
    $this->actingAs($this->companyUser)->get(route('company.dashboard'))->assertOk();
});

it('double-submitting approve is harmless', function () {
    $this->actingAs($this->admin)->post(route('admin.companies.approve', $this->company));
    $at = $this->company->refresh()->approved_at;

    $this->actingAs($this->admin)->post(route('admin.companies.approve', $this->company))
        ->assertRedirect()->assertSessionHas('info');

    expect($this->company->refresh()->approved_at->equalTo($at))->toBeTrue();
});

it('rejects with a reason and validates its length', function () {
    $this->actingAs($this->admin)->post(route('admin.companies.reject', $this->company), ['reason' => str_repeat('x', 501)])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->admin)->post(route('admin.companies.reject', $this->company), ['reason' => 'MOA unsigned'])
        ->assertRedirect()->assertSessionHas('success');

    expect($this->company->refresh()->approval_status)->toBe(ApprovalStatus::Rejected);
    $this->actingAs($this->companyUser)->get(route('account.pending'))->assertOk()->assertSee('not approved');
});

it('is admin-only', function () {
    $this->actingAs(User::factory()->company()->create())->post(route('admin.companies.approve', $this->company))->assertForbidden();
    expect($this->company->refresh()->isApproved())->toBeFalse();
});

it('returns 404 when approving or rejecting a partner company', function () {
    $partner = Company::factory()->partner()->create();

    $this->actingAs($this->admin)->post(route('admin.companies.approve', $partner))->assertNotFound();
    $this->actingAs($this->admin)->post(route('admin.companies.reject', $partner))->assertNotFound();
});

it('double-submitting reject is harmless', function () {
    $this->actingAs($this->admin)->post(route('admin.companies.reject', $this->company), ['reason' => 'MOA unsigned']);

    $this->actingAs($this->admin)->post(route('admin.companies.reject', $this->company))
        ->assertRedirect()->assertSessionHas('info');
});
