<?php

use App\Actions\DeleteDocumentRequest;
use App\Actions\RequestDocument;
use App\Actions\UpdateDocumentRequest;
use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DocumentRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create();
});

it('creates a pending request with a control number and tells the company', function () {
    $request = app(RequestDocument::class)($this->intern, ['document_name' => ' Certificate of Completion ', 'message' => 'For my portfolio.']);

    expect($request->placement_id)->toBe($this->placement->id)->and($request->status)->toBe(DocumentRequestStatus::Pending)
        ->and($request->document_name)->toBe('Certificate of Completion')->and($request->control_no)->toMatch('/^DR-\d{4}-\d{5}$/');
    Notification::assertSentTo($this->placement->company->user, DocumentRequested::class, fn (DocumentRequested $n) => str_contains($n->toArray($this->placement->company->user)['body'], 'Certificate of Completion'));

    expect(fn () => app(RequestDocument::class)(User::factory()->intern()->create(), ['document_name' => 'X']))->toThrow(DomainRuleViolation::class);
});

it('edits and deletes pending requests only', function () {
    $request = app(RequestDocument::class)($this->intern, ['document_name' => 'Acceptance Letter']);

    app(UpdateDocumentRequest::class)($request, ['document_name' => 'Evaluation Form', 'message' => 'Needed by Friday.']);
    expect($request->refresh()->document_name)->toBe('Evaluation Form')->and($request->message)->toBe('Needed by Friday.');

    $request->update(['status' => DocumentRequestStatus::Fulfilled]);
    expect(fn () => app(UpdateDocumentRequest::class)($request, ['document_name' => 'Nope']))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DeleteDocumentRequest::class)($request))->toThrow(DomainRuleViolation::class);

    $pending = DocumentRequest::factory()->for($this->placement)->create();
    app(DeleteDocumentRequest::class)($pending);
    expect(DocumentRequest::whereKey($pending->id)->exists())->toBeFalse();
});
