<?php

namespace App\Http\Controllers\Intern;

use App\Actions\LeaveCompany;
use App\Actions\PlaceIntern;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\JoinCompanyRequest;
use App\Models\Placement;
use App\Services\OjtHoursService;
use App\Support\LeaveWarning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InternshipController extends Controller
{
    public function show(Request $request, OjtHoursService $hours): View
    {
        $user = $request->user()->load('internProfile');
        $placement = $user->activePlacement()->with(['company.user', 'department'])->first();
        $total = $user->internProfile?->total_hours ?? 0;

        return view('intern.internship.show', [
            'placement' => $placement,
            'coInterns' => $placement
                ? Placement::active()->where('company_id', $placement->company_id)->whereKeyNot($placement->id)->with(['intern', 'department'])->get()
                : collect(),
            'acceptedCompanies' => $placement
                ? collect()
                : $user->applications()->where('status', ApplicationStatus::Accepted)->with('posting.company')->get()->map(fn ($a) => $a->posting->company)->unique('id')->values(),
            'warning' => $placement ? LeaveWarning::message($placement->hours_rendered, $hours) : null,
            'progress' => [
                'hours' => $total,
                'required' => $hours->required(),
                'percent' => $hours->progressPercent($total),
                'remaining' => $hours->remaining($total),
                'tier' => $hours->tier($total),
            ],
            'hoursService' => $hours,
        ]);
    }

    public function join(JoinCompanyRequest $request, PlaceIntern $place): RedirectResponse
    {
        $placement = $place($request->user(), $request->validated('company_code'));

        return redirect()->route('intern.internship.show')->with('success', "Welcome to {$placement->company->name}! Your DTRs will now be reviewed by them.");
    }

    public function leave(Request $request, LeaveCompany $leave): RedirectResponse
    {
        $placement = $request->user()->activePlacement()->with('company')->first();

        Gate::authorize('leave', $placement ?? new Placement);

        $leave($request->user());

        return redirect()->route('intern.internship.show')->with('success', "You left {$placement->company->name}. Your approved hours are kept.");
    }
}
