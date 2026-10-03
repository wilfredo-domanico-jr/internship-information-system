<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Services\OjtHoursService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OjtHoursService $hours): View
    {
        $user = $request->user()->load(['internProfile.classSection.adviser', 'activePlacement.company']);
        $total = $user->internProfile?->total_hours ?? 0;

        return view('intern.dashboard', [
            'profile' => $user->internProfile,
            'placement' => $user->activePlacement,
            'section' => $user->internProfile?->classSection,
            'progress' => [
                'hours' => $total,
                'required' => $hours->required(),
                'percent' => $hours->progressPercent($total),
                'remaining' => $hours->remaining($total),
                'tier' => $hours->tier($total),
            ],
        ]);
    }
}
