<?php

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyRegistered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->payload = fn (array $overrides = []) => array_merge([
        'company_name' => 'TechNova Solutions Inc.', 'company_type' => 'IT Services',
        'first_name' => 'Marco', 'last_name' => 'Villanueva', 'email' => 'hr@technova.example',
        'phone' => '09171234567', 'address' => 'Ortigas Center, Pasig City', 'website' => 'https://technova.example',
        'about' => 'Software consultancy.',
        'password' => 'Secret-Pass-123', 'password_confirmation' => 'Secret-Pass-123', 'terms' => '1',
        'permit' => UploadedFile::fake()->create('permit.pdf', 300, 'application/pdf'),
        'moa' => UploadedFile::fake()->create('moa.pdf', 300, 'application/pdf'),
    ], $overrides);
});

it('shows the company registration form', function () {
    $this->get('/register/company')->assertOk()->assertSee('business permit', false);
});

it('registers a pending company, stores the documents and notifies admins', function () {
    $this->post('/register/company', ($this->payload)())->assertRedirect(route('account.pending'));

    $user = User::where('email', 'hr@technova.example')->firstOrFail();
    $company = $user->company;

    $this->assertAuthenticatedAs($user);
    expect($user->member_no)->toStartWith('CMP-')
        ->and($company->approval_status)->toBe(ApprovalStatus::Pending)
        ->and($company->company_code)->toHaveLength(8)
        ->and($company->permit_path)->toBe("companies/{$company->id}/permit.pdf")
        ->and($company->moa_path)->toBe("companies/{$company->id}/moa.pdf");

    Storage::disk('local')->assertExists($company->permit_path);
    Storage::disk('local')->assertExists($company->moa_path);
    Notification::assertSentTo($this->admin, CompanyRegistered::class);
});

it('rejects a non-PDF renamed to .pdf', function () {
    $this->post('/register/company', ($this->payload)(['permit' => UploadedFile::fake()->image('permit.pdf')->mimeType('image/png')]))
        ->assertSessionHasErrors('permit');

    expect(Company::count())->toBe(0);
});

it('rejects documents over the size limit', function () {
    $tooBig = config('wiis.uploads.max_pdf_kb') + 1;

    $this->post('/register/company', ($this->payload)(['moa' => UploadedFile::fake()->create('moa.pdf', $tooBig, 'application/pdf')]))
        ->assertSessionHasErrors('moa');
});

it('requires both documents, the terms and a unique email', function () {
    User::factory()->company()->create(['email' => 'hr@technova.example']);

    $this->post('/register/company', ($this->payload)(['permit' => null, 'terms' => null]))
        ->assertSessionHasErrors(['permit', 'terms', 'email']);
});
