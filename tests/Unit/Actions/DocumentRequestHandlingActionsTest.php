<?php

use App\Actions\DeclineDocumentRequest;
use App\Actions\FulfilDocumentRequest;
use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->request = DocumentRequest::factory()->create();
    $this->intern = $this->request->placement->intern;
});

it('fulfils a pending request with a PDF and tells the intern', function () {
    $fulfilled = app(FulfilDocumentRequest::class)($this->request, UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf'));

    expect($fulfilled->status)->toBe(DocumentRequestStatus::Fulfilled)->and($fulfilled->handled_at)->not->toBeNull()
        ->and($fulfilled->file_path)->toStartWith("document-requests/{$this->request->placement_id}/");
    Storage::disk('local')->assertExists($fulfilled->file_path);
    Notification::assertSentTo($this->intern, DocumentRequestHandled::class, fn (DocumentRequestHandled $n) => str_contains($n->toArray($this->intern)['title'], 'ready'));

    expect(fn () => app(FulfilDocumentRequest::class)($this->request, UploadedFile::fake()->create('x.pdf', 1, 'application/pdf')))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DeclineDocumentRequest::class)($this->request))->toThrow(DomainRuleViolation::class);
});

it('declines a pending request without storing anything', function () {
    app(DeclineDocumentRequest::class)($this->request);

    expect($this->request->refresh()->status)->toBe(DocumentRequestStatus::Declined)->and($this->request->file_path)->toBeNull()->and($this->request->handled_at)->not->toBeNull();
    Notification::assertSentTo($this->intern, DocumentRequestHandled::class, fn (DocumentRequestHandled $n) => str_contains($n->toArray($this->intern)['title'], 'declined'));
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
