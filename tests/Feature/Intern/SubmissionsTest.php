<?php

use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassDocumentSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->section = ClassSection::factory()->create();
    $this->folder = ClassFolder::factory()->for($this->section)->create(['name' => 'Endorsement Letter']);
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
});

it('lists folders on the documents tab and shows the upload form in a folder', function () {
    ClassFolder::factory()->for($this->section)->locked()->create(['name' => 'Weekly Report 1']);

    $this->actingAs($this->intern)->get(route('intern.class.documents'))
        ->assertOk()->assertSee('Endorsement Letter')->assertSee('Weekly Report 1')->assertSee('Locked')->assertSee(route('intern.folders.show', $this->folder));
    $this->actingAs($this->intern)->get(route('intern.folders.show', $this->folder))
        ->assertOk()->assertSee('Endorsement Letter')->assertSee('name="file"', false)->assertSee('name="title"', false);
    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertSee(route('intern.class.documents'));
});

it('uploads a PDF, notifies the adviser and marks late uploads', function () {
    Notification::fake();

    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'My endorsement letter', 'file' => UploadedFile::fake()->create('letter.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('intern.folders.show', $this->folder))->assertSessionHas('success');

    $submission = ClassSubmission::firstOrFail();
    expect($submission->is_late)->toBeFalse()->and($submission->intern_id)->toBe($this->intern->id);
    Storage::disk('local')->assertExists($submission->file_path);
    Notification::assertSentTo($this->section->adviser, ClassDocumentSubmitted::class);

    $this->folder->update(['is_locked' => true]);
    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'Late one', 'file' => UploadedFile::fake()->create('late.pdf', 200, 'application/pdf'),
    ])->assertRedirect();
    expect(ClassSubmission::where('title', 'Late one')->firstOrFail()->is_late)->toBeTrue();

    $this->actingAs($this->intern)->get(route('intern.folders.show', $this->folder))
        ->assertSee('My endorsement letter')->assertSee('Late one')->assertSee('Late')->assertSee(route('files.show', ['class-submission', $submission->id]));
});

it('rejects non-PDF, oversized and untitled uploads', function () {
    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'Doc', 'file' => UploadedFile::fake()->create('doc.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertSessionHasErrors('file');
    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'Doc', 'file' => UploadedFile::fake()->create('big.pdf', config('wiis.uploads.max_pdf_kb') + 1, 'application/pdf'),
    ])->assertSessionHasErrors('file');
    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => '', 'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('title');
    expect(ClassSubmission::count())->toBe(0);
});

it('keeps outsiders out of folders', function () {
    $outsider = User::factory()->intern()->create();

    $this->actingAs($outsider)->get(route('intern.folders.show', $this->folder))->assertForbidden();
    $this->actingAs($outsider)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'Doc', 'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
    ])->assertForbidden();
    $this->actingAs($outsider)->get(route('intern.class.documents'))->assertRedirect(route('intern.class.show'));
});

it('lets the intern withdraw a pending submission but not a reviewed one', function () {
    Storage::disk('local')->put('classroom/x/mine.pdf', 'x');
    $pending = ClassSubmission::factory()->for($this->folder, 'folder')->for($this->intern, 'intern')->create(['file_path' => 'classroom/x/mine.pdf']);
    $approved = ClassSubmission::factory()->for($this->folder, 'folder')->for($this->intern, 'intern')->approved()->create();
    $someoneElses = ClassSubmission::factory()->for($this->folder, 'folder')->create();

    $this->actingAs($this->intern)->delete(route('intern.submissions.destroy', $approved))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.submissions.destroy', $someoneElses))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.submissions.destroy', $pending))->assertRedirect();

    expect(ClassSubmission::whereKey($pending->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('classroom/x/mine.pdf');
});

it('lists all of the intern’s submissions with folder, status and notes', function () {
    ClassSubmission::factory()->for($this->folder, 'folder')->for($this->intern, 'intern')->declined()->create(['title' => 'Old resume', 'reviewer_note' => 'Please use the template.']);
    ClassSubmission::factory()->for($this->folder, 'folder')->create(['title' => 'Not mine']);

    $this->actingAs($this->intern)->get(route('intern.submissions.index'))
        ->assertOk()->assertSee('Old resume')->assertSee('Endorsement Letter')->assertSee('Declined')->assertSee('Please use the template.')->assertDontSee('Not mine');
});
