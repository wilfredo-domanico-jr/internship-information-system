<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\OjtHoursService;
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
}
