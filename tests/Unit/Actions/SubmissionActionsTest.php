<?php

use App\Actions\DeleteSubmission;
use App\Actions\SubmitClassDocument;
use App\Actions\ToggleFolderLock;
use App\Enums\SubmissionStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassFolder;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassDocumentSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->folder = ClassFolder::factory()->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->intern->internProfile->update(['class_section_id' => $this->folder->class_section_id]);
});

it('stores the PDF privately, creates a pending submission and notifies the adviser', function () {
    $submission = app(SubmitClassDocument::class)($this->folder, $this->intern, ' Endorsement letter ', UploadedFile::fake()->create('letter.pdf', 120, 'application/pdf'));

    expect($submission->status)->toBe(SubmissionStatus::Pending)->and($submission->is_late)->toBeFalse()
        ->and($submission->title)->toBe('Endorsement letter')->and($submission->intern_id)->toBe($this->intern->id)
        ->and($submission->file_path)->toStartWith("classroom/{$this->folder->class_section_id}/folders/{$this->folder->id}/{$this->intern->id}/")->toEndWith('.pdf');
    Storage::disk('local')->assertExists($submission->file_path);
    Notification::assertSentTo($this->folder->classSection->adviser, ClassDocumentSubmitted::class, function (ClassDocumentSubmitted $n) {
        $data = $n->toArray($this->folder->classSection->adviser);

        return str_contains($data['body'], 'Maria Santos') && $data['url'] === route('adviser.folders.show', $this->folder);
    });
});

it('flags uploads into a locked folder as late, and unlocking later keeps the flag', function () {
    app(ToggleFolderLock::class)($this->folder);

    $submission = app(SubmitClassDocument::class)($this->folder->refresh(), $this->intern, 'Weekly report', UploadedFile::fake()->create('w.pdf', 10, 'application/pdf'));
    expect($submission->is_late)->toBeTrue();

    app(ToggleFolderLock::class)($this->folder);
    expect($submission->refresh()->is_late)->toBeTrue();
    expect(app(SubmitClassDocument::class)($this->folder->refresh(), $this->intern, 'Again', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))->is_late)->toBeFalse();
});

it('deletes a pending submission with its file but refuses reviewed ones', function () {
    $submission = app(SubmitClassDocument::class)($this->folder, $this->intern, 'Doc', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'));
    $path = $submission->file_path;

    app(DeleteSubmission::class)($submission);
    expect(ClassSubmission::whereKey($submission->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);

    $approved = ClassSubmission::factory()->for($this->folder, 'folder')->approved()->create();
    expect(fn () => app(DeleteSubmission::class)($approved))->toThrow(DomainRuleViolation::class);
    expect(ClassSubmission::whereKey($approved->id)->exists())->toBeTrue();
});
