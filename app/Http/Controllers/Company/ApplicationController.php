<?php

namespace App\Http\Controllers\Company;

use App\Actions\DecideApplication;
use App\Actions\ScheduleInterview;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\DeclineApplicationRequest;
use App\Http\Requests\Company\ScheduleInterviewRequest;
use App\Models\Application;
use App\Services\OjtHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function show(Application $application, OjtHoursService $hours): View
    {
        Gate::authorize('view', $application);

        $application->load(['intern.internProfile.classSection.adviser', 'posting', 'interview']);

        return view('company.applications.show', [
            'application' => $application,
            'intern' => $application->intern,
            'profile' => $application->intern->internProfile,
            'hours' => $hours,
        ]);
    }

    public function interview(ScheduleInterviewRequest $request, Application $application, ScheduleInterview $schedule): RedirectResponse
    {
        $schedule($application, $request->validated());

        return redirect()->route('company.applications.show', $application)->with('success', 'Interview scheduled. The applicant has been notified.');
    }

    public function decline(DeclineApplicationRequest $request, Application $application, DecideApplication $decide): RedirectResponse
    {
        $decide($application, ApplicationStatus::Declined, $request->validated('reason'));

        return redirect()->route('company.postings.applicants', $application->posting)->with('success', "{$application->intern->name}'s application was declined.");
    }

    public function accept(Application $application, DecideApplication $decide): RedirectResponse
    {
        Gate::authorize('decide', $application);

        $decide($application, ApplicationStatus::Accepted);

        return back()->with('success', "{$application->intern->name} was accepted and told to join with your company code.");
    }
}
