<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\ClassFolder;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassDocumentSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Uploads are always accepted; a locked folder only marks the submission late. */
class SubmitClassDocument
{
    public function __invoke(ClassFolder $folder, User $intern, string $title, UploadedFile $file): ClassSubmission
    {
        $folder->loadMissing('classSection.adviser');

        $path = $file->storeAs(
            "classroom/{$folder->class_section_id}/folders/{$folder->id}/{$intern->id}",
            Str::uuid().'.pdf',
            'local',
        );

        $submission = $folder->submissions()->create([
            'intern_id' => $intern->id,
            'title' => trim($title),
            'file_path' => $path,
            'status' => SubmissionStatus::Pending,
            'is_late' => $folder->is_locked,
        ]);

        $folder->classSection->adviser?->notify(new ClassDocumentSubmitted($submission->load('intern')));

        return $submission;
    }
}
