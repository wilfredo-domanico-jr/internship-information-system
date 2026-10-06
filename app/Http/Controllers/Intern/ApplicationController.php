<?php

namespace App\Http\Controllers\Intern;

use App\Actions\ApplyToPosting;
use App\Actions\CancelApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\ApplyRequest;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Support\ApplicationTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function store(ApplyRequest $request, InternshipPosting $posting, ApplyToPosting $apply): RedirectResponse
    {
        $apply($request->user(), $posting, $request->file('resume'), $request->file('endorsement'));

        return redirect()->route('intern.postings.show', $posting)->with('success', "Application sent to {$posting->company->name}. You will be notified about the next step.");
    }

    public function index(Request $request): View
    {
        $applications = $request->user()->applications()->with(['posting.company', 'interview'])->get();

        return view('intern.applications.index', [
            'applications' => $applications,
            'timelines' => $applications->mapWithKeys(fn (Application $a) => [$a->id => ApplicationTimeline::steps($a)]),
        ]);
    }

    public function cancel(Application $application, CancelApplication $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $application);

        $cancel($application);

        return redirect()->route('intern.applications.index')->with('success', 'Application cancelled.');
    }
}
