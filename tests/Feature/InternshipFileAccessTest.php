<?php

use App\Models\Application;
use App\Models\Certificate;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->companyUser = User::factory()->company()->create();
    $this->intern = User::factory()->intern()->create();
    $posting = InternshipPosting::factory()->for($this->companyUser->company)->create();
    foreach (['resume', 'endorsement', 'dtr', 'request', 'cert'] as $name) {
        Storage::disk('local')->put("t/{$name}.pdf", '%PDF-1.4 fake');
    }
    $placement = Placement::factory()->for($this->intern, 'intern')->for($this->companyUser->company)->create();
    $this->application = Application::factory()->for($posting, 'posting')->for($this->intern, 'intern')->create(['resume_path' => 't/resume.pdf', 'endorsement_path' => 't/endorsement.pdf']);
    $this->dtr = Dtr::factory()->for($placement)->create(['file_path' => 't/dtr.pdf']);
    $this->request = DocumentRequest::factory()->for($placement)->create(['file_path' => 't/request.pdf']);
    $this->certificate = Certificate::factory()->for($placement)->create(['file_path' => 't/cert.pdf']);
});

it('serves every internship file kind to its intern, its company and admins', function () {
    $kinds = [
        ['application-resume', $this->application->id], ['application-endorsement', $this->application->id],
        ['dtr', $this->dtr->id], ['document-request', $this->request->id], ['certificate', $this->certificate->id],
    ];

    foreach ([$this->intern, $this->companyUser, User::factory()->admin()->create()] as $user) {
        foreach ($kinds as [$kind, $id]) {
            $this->actingAs($user)->get(route('files.show', [$kind, $id]))
                ->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }
});

it('forbids other interns, other companies and advisers', function () {
    foreach ([User::factory()->intern()->create(), User::factory()->company()->create(), User::factory()->adviser()->create()] as $user) {
        $this->actingAs($user)->get(route('files.show', ['application-resume', $this->application->id]))->assertForbidden();
        $this->actingAs($user)->get(route('files.show', ['dtr', $this->dtr->id]))->assertForbidden();
        $this->actingAs($user)->get(route('files.show', ['document-request', $this->request->id]))->assertForbidden();
        $this->actingAs($user)->get(route('files.show', ['certificate', $this->certificate->id]))->assertForbidden();
    }
});

it('returns 404 for a document request that has no file yet', function () {
    $this->request->update(['file_path' => null]);

    $this->actingAs($this->intern)->get(route('files.show', ['document-request', $this->request->id]))->assertNotFound();
});
