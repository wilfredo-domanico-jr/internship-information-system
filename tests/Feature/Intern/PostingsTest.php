<?php

use App\Actions\ApplyToPosting;
use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->intern = User::factory()->intern()->create();
    $this->company = Company::factory()->registered()->create(['name' => 'TechNova']);
    $this->posting = InternshipPosting::factory()->for($this->company)->create(['title' => 'Web Dev Intern', 'city' => 'Pasig']);
});

it('lists postings that accept applications, with search and city filter', function () {
    InternshipPosting::factory()->closed()->create(['title' => 'Closed Intern']);
    InternshipPosting::factory()->create(['title' => 'Expired Intern', 'closing_date' => now()->subDay()->toDateString()]);
    InternshipPosting::factory()->create(['title' => 'Data Intern', 'city' => 'Makati']);

    $this->actingAs($this->intern)->get(route('intern.postings.index'))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee('TechNova')->assertSee('Data Intern')->assertDontSee('Closed Intern')->assertDontSee('Expired Intern');
    $this->actingAs($this->intern)->get(route('intern.postings.index', ['q' => 'data']))->assertSee('Data Intern')->assertDontSee('Web Dev Intern');
    $this->actingAs($this->intern)->get(route('intern.postings.index', ['city' => 'Pasig']))->assertSee('Web Dev Intern')->assertDontSee('Data Intern');
});

it('shows a posting with the apply form, and hides closed ones', function () {
    $this->actingAs($this->intern)->get(route('intern.postings.show', $this->posting))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee($this->posting->contact_name)->assertSee('name="resume"', false)->assertSee('name="endorsement"', false);

    $closed = InternshipPosting::factory()->closed()->create();
    $this->actingAs($this->intern)->get(route('intern.postings.show', $closed))->assertForbidden();
});

it('applies with two PDFs and notifies the company', function () {
    Notification::fake();

    $this->actingAs($this->intern)->post(route('intern.applications.store', $this->posting), [
        'resume' => UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf'),
        'endorsement' => UploadedFile::fake()->create('endorsement.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('intern.postings.show', $this->posting))->assertSessionHas('success');

    $application = Application::firstOrFail();
    Storage::disk('local')->assertExists($application->resume_path);
    Notification::assertSentTo($this->company->user, ApplicationReceived::class);

    $this->actingAs($this->intern)->get(route('intern.postings.show', $this->posting))->assertSee('already applied')->assertDontSee('name="resume"', false);
});

it('validates the files and explains why an intern cannot apply', function () {
    $this->actingAs($this->intern)->post(route('intern.applications.store', $this->posting), [
        'resume' => UploadedFile::fake()->create('resume.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertSessionHasErrors(['resume', 'endorsement']);

    Placement::factory()->for($this->intern, 'intern')->create();
    $this->actingAs($this->intern)->get(route('intern.postings.show', $this->posting))->assertSee('already placed')->assertDontSee('name="resume"', false);
    $this->actingAs($this->intern)->from(route('intern.postings.show', $this->posting))->post(route('intern.applications.store', $this->posting), [
        'resume' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
        'endorsement' => UploadedFile::fake()->create('e.pdf', 10, 'application/pdf'),
    ])->assertRedirect(route('intern.postings.show', $this->posting))->assertSessionHas('error');
    expect(Application::count())->toBe(0);
});

it('hides postings of rejected or disabled companies', function () {
    $rejected = InternshipPosting::factory()->for(Company::factory()->registered()->create(['approval_status' => ApprovalStatus::Rejected]))->create(['title' => 'Rejected Co Intern']);
    $disabledCompany = Company::factory()->registered()->create();
    $disabledCompany->user->update(['status' => AccountStatus::Disabled]);
    $disabled = InternshipPosting::factory()->for($disabledCompany)->create(['title' => 'Disabled Co Intern']);

    $this->actingAs($this->intern)->get(route('intern.postings.index'))->assertOk()->assertSee('Web Dev Intern')->assertDontSee('Rejected Co Intern')->assertDontSee('Disabled Co Intern');
    $this->actingAs($this->intern)->get(route('intern.postings.show', $rejected))->assertForbidden();
    $this->actingAs($this->intern)->get(route('intern.postings.show', $disabled))->assertForbidden();
    expect(ApplyToPosting::blocker($this->intern, $rejected))->toContain('closed')
        ->and(ApplyToPosting::blocker($this->intern, $disabled))->toContain('closed');
});
