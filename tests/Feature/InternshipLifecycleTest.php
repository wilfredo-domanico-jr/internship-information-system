<?php

use App\Enums\ApplicationStatus;
use App\Enums\HoursTier;
use App\Models\Certificate;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\User;
use App\Services\OjtHoursService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('runs the whole internship lifecycle from application to certificate', function () {
    Storage::fake('local');
    $hours = app(OjtHoursService::class);
    $companyUser = User::factory()->company()->create();
    $company = $companyUser->company;
    $intern = User::factory()->intern()->create();
    $posting = InternshipPosting::factory()->for($company)->create();
    $pdf = fn (string $name) => UploadedFile::fake()->create($name, 50, 'application/pdf');

    // 1. intern2 applies
    $this->actingAs($intern)->post(route('intern.applications.store', $posting), ['resume' => $pdf('resume.pdf'), 'endorsement' => $pdf('endorsement.pdf')])->assertSessionHas('success');
    $application = $posting->applications()->firstOrFail();
    $this->actingAs($companyUser)->get(route('company.postings.applicants', $posting))->assertSee($intern->name);

    // 2. company schedules an interview, 3. accepts
    $this->actingAs($companyUser)->post(route('company.applications.interview', $application), ['title' => 'Initial interview', 'venue' => 'Google Meet', 'link' => '', 'scheduled_on' => now()->addDays(2)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '10:30', 'notes' => ''])->assertSessionHas('success');
    $this->actingAs($intern)->get(route('intern.applications.index'))->assertSee('Google Meet');
    $this->actingAs($companyUser)->post(route('company.applications.accept', $application))->assertSessionHas('success');
    expect($application->refresh()->status)->toBe(ApplicationStatus::Accepted)->and($intern->hasActivePlacement())->toBeFalse();

    // 4. intern joins by code
    $this->actingAs($intern)->post(route('intern.internship.join'), ['company_code' => $company->company_code])->assertSessionHas('success');
    $placement = $intern->activePlacement()->firstOrFail();
    $placement->update(['started_at' => now()->subDays(120)->toDateString()]);
    $this->actingAs($companyUser)->get(route('company.interns.index'))->assertSee($intern->name);

    // 5. submits DTRs, 6. company approves, 7. hours reach the requirement
    $chunk = (int) ceil($hours->required() / 3);
    foreach (range(1, 3) as $i) {
        $this->actingAs($intern)->post(route('intern.dtrs.store'), ['period_from' => now()->subDays(30 * $i + 20)->toDateString(), 'period_to' => now()->subDays(30 * $i)->toDateString(), 'hours' => $chunk, 'absences' => 0, 'file' => $pdf("dtr{$i}.pdf")])->assertSessionHas('success');
    }
    foreach (Dtr::where('placement_id', $placement->id)->get() as $dtr) {
        $this->actingAs($companyUser)->post(route('company.dtrs.approve', $dtr))->assertSessionHas('success');
        $this->actingAs($companyUser)->from(route('company.dtrs.index'))->post(route('company.dtrs.approve', $dtr))->assertSessionHas('error');
    }
    $total = $intern->internProfile->refresh()->total_hours;
    expect($total)->toBeGreaterThanOrEqual($hours->required())->and($placement->refresh()->hours_rendered)->toBe($total)->and($hours->tier($total))->toBe(HoursTier::Complete);
    $this->actingAs($intern)->get(route('intern.internship.show'))->assertSee('Complete')->assertSee('completed');

    // 8. certificate issued, 9. intern sees it
    $this->actingAs($companyUser)->get(route('company.certificates.index'))->assertSee($intern->name);
    $this->actingAs($companyUser)->post(route('company.certificates.store', $placement), ['file' => $pdf('certificate.pdf')])->assertSessionHas('success');
    $certificate = Certificate::firstOrFail();
    expect($certificate->hours_at_issue)->toBe($total);
    $this->actingAs($intern)->get(route('intern.certificates.index'))->assertSee($company->name)->assertSee(route('files.show', ['certificate', $certificate->id]));
    $this->actingAs($intern)->get(route('files.show', ['certificate', $certificate->id]))->assertOk();
    expect($intern->notifications()->count())->toBeGreaterThanOrEqual(6);
});
