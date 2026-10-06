<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\ClaimClass;
use App\Actions\CreateClassSection;
use App\Actions\LeaveClass;
use App\Actions\UpdateClassSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ClassSectionRequest;
use App\Http\Requests\Classroom\JoinClassRequest;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClassSectionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('adviser.classes.index', [
            'classes' => $user->advisedClasses()->active()
                ->withCount(['internProfiles', 'folders', 'announcements'])
                ->orderByDesc('school_year')->orderBy('course_code')->orderBy('section')
                ->get(),
            'past' => ClassAdviserLog::query()->with('classSection')
                ->where('adviser_id', $user->id)->whereNotNull('left_at')
                ->latest('left_at')->get(),
        ]);
    }

    public function create(): View
    {
        return view('adviser.classes.create', ['days' => ClassSectionRequest::DAYS]);
    }

    public function store(ClassSectionRequest $request, CreateClassSection $create): RedirectResponse
    {
        $section = $create($request->validated(), $request->user());

        return redirect()->route('adviser.classes.index')
            ->with('success', "{$section->display_name} was created. Share the join code {$section->join_code} with your interns.");
    }

    public function edit(ClassSection $classSection): View
    {
        Gate::authorize('manage', $classSection);

        return view('adviser.classes.edit', ['class' => $classSection, 'days' => ClassSectionRequest::DAYS]);
    }

    public function update(ClassSectionRequest $request, ClassSection $classSection, UpdateClassSection $update): RedirectResponse
    {
        $update($classSection, $request->validated());

        return redirect()->route('adviser.classes.index')->with('success', "{$classSection->display_name} was updated.");
    }

    public function join(JoinClassRequest $request, ClaimClass $claim): RedirectResponse
    {
        $section = $claim($request->validated('join_code'), $request->user());

        return redirect()->route('adviser.classes.index')->with('success', "You are now the adviser of {$section->display_name}.");
    }

    public function leave(ClassSection $classSection, Request $request, LeaveClass $leave): RedirectResponse
    {
        Gate::authorize('manage', $classSection);

        $leave($classSection, $request->user());

        return redirect()->route('adviser.classes.index')->with('success', "You left {$classSection->display_name}. Another adviser can claim it with its join code.");
    }
}
