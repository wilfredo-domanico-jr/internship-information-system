<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\ClaimClass;
use App\Actions\CreateClassSection;
use App\Actions\LeaveClass;
use App\Actions\UpdateClassSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ClassSectionRequest;
use App\Http\Requests\Classroom\JoinClassRequest;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\InternProfile;
use App\Services\OjtHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function show(ClassSection $classSection): View
    {
        Gate::authorize('view', $classSection);

        return view('adviser.classes.show', [
            'class' => $classSection->loadCount('internProfiles'),
            'announcements' => $classSection->announcements()->with(['author', 'comments.author'])->paginate(10)
                ->through(function (Announcement $announcement) use ($classSection) {
                    $announcement->setRelation('classSection', $classSection);
                    $announcement->comments->each(fn (AnnouncementComment $comment) => $comment->setRelation('announcement', $announcement));

                    return $announcement;
                }),
        ]);
    }

    public function people(ClassSection $classSection, OjtHoursService $hours): View
    {
        Gate::authorize('view', $classSection);

        return view('adviser.classes.people', [
            'class' => $classSection->loadCount('internProfiles'),
            'profiles' => $this->roster($classSection),
            'hours' => $hours,
        ]);
    }

    public function print(ClassSection $classSection, OjtHoursService $hours): View
    {
        Gate::authorize('view', $classSection);

        return view('adviser.classes.print', [
            'class' => $classSection,
            'profiles' => $this->roster($classSection),
            'hours' => $hours,
        ]);
    }

    /** @return Collection<int, InternProfile> sorted by surname, with user, active placement and company loaded */
    private function roster(ClassSection $section): Collection
    {
        return $section->internProfiles()->with(['user.activePlacement.company'])->get()
            ->sortBy(fn (InternProfile $profile) => mb_strtolower($profile->user->last_name.' '.$profile->user->first_name))
            ->values();
    }
}
