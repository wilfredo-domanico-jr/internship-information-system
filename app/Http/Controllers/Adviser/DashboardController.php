<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\InternProfile;
use App\Services\OjtHoursService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OjtHoursService $hours): View
    {
        $classes = $request->user()->advisedClasses()->active()->withCount('internProfiles')->get();
        $interns = InternProfile::query()->whereIn('class_section_id', $classes->pluck('id'));

        return view('adviser.dashboard', [
            'classes' => $classes,
            'stats' => [
                'classes' => $classes->count(),
                'interns' => (clone $interns)->count(),
                'completed' => (clone $interns)->where('total_hours', '>=', $hours->required())->count(),
            ],
        ]);
    }
}
