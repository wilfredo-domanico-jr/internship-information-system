<?php

use App\Actions\IssueCertificate;
use App\Exceptions\DomainRuleViolation;
use App\Models\Certificate;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\CertificateIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->min = (int) config('wiis.hours.certificate_min');
    $this->pdf = fn () => UploadedFile::fake()->create('certificate.pdf', 80, 'application/pdf');
});

it('issues and re-issues a certificate to an eligible placement and tells the intern', function () {
    $placement = Placement::factory()->create(['hours_rendered' => $this->min]);

    $first = app(IssueCertificate::class)($placement, ($this->pdf)());
    expect($first->hours_at_issue)->toBe($this->min)->and($first->issued_at)->not->toBeNull()->and($first->file_path)->toStartWith("certificates/{$placement->id}/");
    Storage::disk('local')->assertExists($first->file_path);
    Notification::assertSentTo($placement->intern, CertificateIssued::class);

    $placement->update(['hours_rendered' => $this->min + 50]);
    $second = app(IssueCertificate::class)($placement->refresh(), ($this->pdf)());
    expect($second->hours_at_issue)->toBe($this->min + 50)->and(Certificate::where('placement_id', $placement->id)->count())->toBe(2);
});

it('judges eligibility by hours at this company, not the intern total', function () {
    $intern = User::factory()->intern()->create();
    $intern->internProfile()->update(['total_hours' => 300]);
    $here = Placement::factory()->for($intern, 'intern')->create(['hours_rendered' => $this->min - 1]);

    expect(fn () => app(IssueCertificate::class)($here, ($this->pdf)()))->toThrow(DomainRuleViolation::class);
    expect(Certificate::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('may issue to an ended placement that reached the minimum', function () {
    $ended = Placement::factory()->ended()->create(['hours_rendered' => $this->min + 10]);

    expect(app(IssueCertificate::class)($ended, ($this->pdf)())->placement_id)->toBe($ended->id);
});
