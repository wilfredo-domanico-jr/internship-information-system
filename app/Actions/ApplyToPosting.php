<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyToPosting
{
    /** Why this intern cannot apply right now, or null when they can. */
    public static function blocker(User $intern, InternshipPosting $posting): ?string
    {
        if (! $posting->isAcceptingApplications()) {
            return 'This posting is closed to new applications.';
        }

        if ($intern->hasActivePlacement()) {
            return 'You are already placed with a company. Leave it first if you want to apply elsewhere.';
        }

        if ($posting->applications()->where('intern_id', $intern->id)->exists()) {
            return 'You have already applied to this posting.';
        }

        return null;
    }

    public function __invoke(User $intern, InternshipPosting $posting, UploadedFile $resume, UploadedFile $endorsement): Application
    {
        if ($reason = self::blocker($intern, $posting)) {
            throw new DomainRuleViolation($reason);
        }

        $id = (string) Str::uuid();
        $resumePath = $resume->storeAs("applications/{$intern->id}", "{$id}-resume.pdf", 'local');
        $endorsementPath = $endorsement->storeAs("applications/{$intern->id}", "{$id}-endorsement.pdf", 'local');

        $application = DB::transaction(fn () => $posting->applications()->create([
            'intern_id' => $intern->id,
            'resume_path' => $resumePath,
            'endorsement_path' => $endorsementPath,
            'status' => ApplicationStatus::Pending,
        ]));

        $posting->company->user?->notify(new ApplicationReceived($application->load('intern')));

        return $application;
    }
}
