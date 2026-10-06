<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\ReviewClassSubmission;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\DeclineSubmissionRequest;
use App\Models\ClassSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubmissionReviewController extends Controller
{
    public function approve(Request $request, ClassSubmission $submission, ReviewClassSubmission $review): RedirectResponse
    {
        Gate::authorize('review', $submission);

        $review($submission, $request->user(), SubmissionStatus::Approved);

        return back()->with('success', "“{$submission->title}” approved.");
    }

    public function decline(DeclineSubmissionRequest $request, ClassSubmission $submission, ReviewClassSubmission $review): RedirectResponse
    {
        $review($submission, $request->user(), SubmissionStatus::Declined, $request->validated('note'));

        return back()->with('success', "“{$submission->title}” declined. The intern has been notified.");
    }
}
