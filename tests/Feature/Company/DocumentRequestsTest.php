<?php

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentRequest;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DocumentRequestHandled;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->company()->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->placement = Placement::factory()->for($this->intern, 'intern')->for($this->user->company)->create();
});

it('groups requests by status and shows the intern and message', function () {
    $pending = DocumentRequest::factory()->for($this->placement)->create(['document_name' => 'Acceptance Letter', 'message' => 'For enrollment.']);
    DocumentRequest::factory()->for($this->placement)->create(['document_name' => 'Old Form', 'status' => 'declined', 'handled_at' => now()]);
    DocumentRequest::factory()->create(['document_name' => 'Not Mine']);

    $this->actingAs($this->user)->get(route('company.requests.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('Acceptance Letter')->assertSee('For enrollment.')->assertSee($pending->control_no)
        ->assertSee(route('company.requests.fulfil', $pending))->assertSee(route('company.requests.decline', $pending))->assertDontSee('Old Form')->assertDontSee('Not Mine');
    $this->actingAs($this->user)->get(route('company.requests.index', ['status' => 'declined']))->assertSee('Old Form');
    $this->actingAs($this->user)->get(route('company.requests.index', ['status' => 'bogus']))->assertNotFound();
});

it('fulfils with a PDF, declines, and keeps other companies out', function () {
    Notification::fake();
    $a = DocumentRequest::factory()->for($this->placement)->create();
    $b = DocumentRequest::factory()->for($this->placement)->create();

    $this->actingAs($this->user)->post(route('company.requests.fulfil', $a), ['file' => UploadedFile::fake()->create('f.txt', 1, 'text/plain')])->assertSessionHasErrors('file');
    $this->actingAs($this->user)->post(route('company.requests.fulfil', $a), ['file' => UploadedFile::fake()->create('f.pdf', 50, 'application/pdf')])->assertRedirect()->assertSessionHas('success');
    expect($a->refresh()->status)->toBe(DocumentRequestStatus::Fulfilled);
    Storage::disk('local')->assertExists($a->file_path);
    Notification::assertSentTo($this->intern, DocumentRequestHandled::class);
    $this->actingAs($this->intern)->get(route('files.show', ['document-request', $a->id]))->assertOk();

    $this->actingAs($this->user)->post(route('company.requests.decline', $b))->assertRedirect()->assertSessionHas('success');
    expect($b->refresh()->status)->toBe(DocumentRequestStatus::Declined);

    $other = User::factory()->company()->create();
    $c = DocumentRequest::factory()->for($this->placement)->create();
    $this->actingAs($other)->post(route('company.requests.fulfil', $c), ['file' => UploadedFile::fake()->create('f.pdf', 1, 'application/pdf')])->assertForbidden();
    $this->actingAs($other)->post(route('company.requests.decline', $c))->assertForbidden();
    expect($c->refresh()->status)->toBe(DocumentRequestStatus::Pending);
});
