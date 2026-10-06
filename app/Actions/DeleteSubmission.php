<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassSubmission;
use Illuminate\Support\Facades\Storage;

class DeleteSubmission
{
    public function __invoke(ClassSubmission $submission): void
    {
        if ($submission->status !== SubmissionStatus::Pending) {
            throw new DomainRuleViolation('Only pending submissions can be withdrawn.');
        }

        $path = $submission->file_path;

        $submission->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
