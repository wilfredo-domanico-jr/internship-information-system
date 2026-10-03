<?php

use App\Actions\ApproveCompany;
use App\Actions\RejectCompany;
use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Notifications\CompanyApproved;
use App\Notifications\CompanyRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->companyUser = User::factory()->company()->create();
    $this->company = tap($this->companyUser->company)->update(['approval_status' => ApprovalStatus::Pending, 'approved_at' => null, 'approved_by' => null]);
});

it('approves a pending company, records who did it and notifies the company', function () {
    $changed = app(ApproveCompany::class)($this->company, $this->admin);

    $this->company->refresh();
    expect($changed)->toBeTrue()
        ->and($this->company->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($this->company->approved_by)->toBe($this->admin->id)
        ->and($this->company->approved_at)->not->toBeNull();
    Notification::assertSentTo($this->companyUser, CompanyApproved::class, function (CompanyApproved $n) {
        $data = $n->toArray($this->companyUser);
        expect($data)->toHaveKeys(['title', 'body', 'url', 'icon'])->and($data['url'])->toBe(route('company.dashboard'));
        expect($n->via($this->companyUser))->toBe(['database', 'mail']);

        return true;
    });
});

it('is a no-op when approving an already approved company', function () {
    app(ApproveCompany::class)($this->company, $this->admin);
    $firstApprovedAt = $this->company->refresh()->approved_at;
    Notification::fake();

    $changed = app(ApproveCompany::class)($this->company->refresh(), User::factory()->admin()->create());

    expect($changed)->toBeFalse()
        ->and($this->company->refresh()->approved_at->equalTo($firstApprovedAt))->toBeTrue()
        ->and($this->company->approved_by)->toBe($this->admin->id);
    Notification::assertNothingSent();
});

it('rejects with a reason and notifies the company', function () {
    $changed = app(RejectCompany::class)($this->company, $this->admin, 'Permit has expired.');

    expect($changed)->toBeTrue()
        ->and($this->company->refresh()->approval_status)->toBe(ApprovalStatus::Rejected)
        ->and($this->company->approved_at)->toBeNull();
    Notification::assertSentTo($this->companyUser, CompanyRejected::class, fn (CompanyRejected $n) => str_contains($n->toArray($this->companyUser)['body'], 'Permit has expired.'));
});

it('can re-approve a rejected company', function () {
    app(RejectCompany::class)($this->company, $this->admin, null);

    expect(app(ApproveCompany::class)($this->company->refresh(), $this->admin))->toBeTrue()
        ->and($this->company->refresh()->isApproved())->toBeTrue();
});
