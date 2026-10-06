<?php

namespace App\Http\Controllers\Company;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\InternshipPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicantController extends Controller
{
    public const GROUPS = ['pending', 'for_interview', 'accepted', 'declined'];

    public function __invoke(Request $request, InternshipPosting $posting): View
    {
        Gate::authorize('manage', $posting);

        $group = (string) $request->query('status', 'pending');
        abort_unless(in_array($group, self::GROUPS, true), 404);

        // Spec: only unplaced interns appear as applicants.
        $base = Application::query()->where('internship_posting_id', $posting->id)->whereDoesntHave('intern.activePlacement');

        $counts = collect(self::GROUPS)->mapWithKeys(fn (string $g) => [$g => (clone $base)->where('status', ApplicationStatus::from($g))->count()])->all();

        return view('company.applicants.index', [
            'posting' => $posting,
            'group' => $group,
            'counts' => $counts,
            'applications' => (clone $base)->where('status', ApplicationStatus::from($group))
                ->with(['intern.internProfile.classSection', 'interview'])
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }
}
