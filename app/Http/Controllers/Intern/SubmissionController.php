<?php

namespace App\Http\Controllers\Intern;

use App\Actions\DeleteSubmission;
use App\Actions\SubmitClassDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\SubmitDocumentRequest;
use App\Models\ClassFolder;
use App\Models\ClassSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        return view('intern.submissions.index', [
            'submissions' => ClassSubmission::query()
                ->where('intern_id', $request->user()->id)
                ->with(['folder.classSection', 'reviewer'])
                ->latest()->paginate(20),
        ]);
    }

    public function store(SubmitDocumentRequest $request, ClassFolder $folder, SubmitClassDocument $submit): RedirectResponse
    {
        $submission = $submit($folder, $request->user(), $request->validated('title'), $request->file('file'));

        return redirect()->route('intern.folders.show', $folder)->with('success', $submission->is_late
            ? 'Uploaded. This folder is locked, so the submission is marked late.'
            : 'Uploaded. Your adviser will review it.');
    }

    public function destroy(ClassSubmission $submission, DeleteSubmission $delete): RedirectResponse
    {
        Gate::authorize('delete', $submission);

        $delete($submission);

        return back()->with('success', 'Submission withdrawn.');
    }
}
