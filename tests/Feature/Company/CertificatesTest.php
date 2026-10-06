<?php

use App\Models\Certificate;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\CertificateIssued;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->company()->create();
    $this->company = $this->user->company;
    $this->min = (int) config('wiis.hours.certificate_min');
});

it('lists eligible interns with their existing certificates', function () {
    $eligible = Placement::factory()->for($this->company)->create(['hours_rendered' => $this->min, 'intern_id' => User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos'])->id]);
    Certificate::factory()->for($eligible)->create(['hours_at_issue' => $this->min]);
    Placement::factory()->for($this->company)->create(['hours_rendered' => $this->min - 1, 'intern_id' => User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Short'])->id]);
    Placement::factory()->create(['hours_rendered' => 400, 'intern_id' => User::factory()->intern()->create(['first_name' => 'Other', 'last_name' => 'Company'])->id]);

    $this->actingAs($this->user)->get(route('company.certificates.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('Re-issue')->assertSee(route('company.certificates.store', $eligible))
        ->assertDontSee('Juan Short')->assertDontSee('Other Company');
});

it('issues a certificate from the page and forbids other companies and ineligible placements', function () {
    Notification::fake();
    $eligible = Placement::factory()->for($this->company)->create(['hours_rendered' => $this->min]);
    $short = Placement::factory()->for($this->company)->create(['hours_rendered' => 10]);

    $this->actingAs($this->user)->post(route('company.certificates.store', $eligible), ['file' => UploadedFile::fake()->create('c.txt', 1, 'text/plain')])->assertSessionHasErrors('file');
    $this->actingAs($this->user)->post(route('company.certificates.store', $eligible), ['file' => UploadedFile::fake()->create('c.pdf', 80, 'application/pdf')])
        ->assertRedirect(route('company.certificates.index'))->assertSessionHas('success');
    $certificate = Certificate::firstOrFail();
    Storage::disk('local')->assertExists($certificate->file_path);
    Notification::assertSentTo($eligible->intern, CertificateIssued::class);

    $this->actingAs($this->user)->from(route('company.certificates.index'))->post(route('company.certificates.store', $short), ['file' => UploadedFile::fake()->create('c.pdf', 1, 'application/pdf')])
        ->assertRedirect(route('company.certificates.index'))->assertSessionHas('error');
    $this->actingAs(User::factory()->company()->create())->post(route('company.certificates.store', $eligible), ['file' => UploadedFile::fake()->create('c.pdf', 1, 'application/pdf')])->assertForbidden();
    expect(Certificate::count())->toBe(1);
});
