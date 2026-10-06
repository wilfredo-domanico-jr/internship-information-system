<?php

namespace App\Http\Controllers\Intern;

use App\Actions\JoinClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\JoinClassRequest;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassSection;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function show(Request $request): View
    {
        $section = $this->currentSection($request->user());

        if (! $section) {
            return view('intern.class.join');
        }

        return view('intern.class.show', [
            'class' => $section,
            'announcements' => $section->announcements()->with(['author', 'comments.author'])->paginate(10)
                ->through(function (Announcement $announcement) use ($section) {
                    $announcement->setRelation('classSection', $section);
                    $announcement->comments->each(fn (AnnouncementComment $comment) => $comment->setRelation('announcement', $announcement));

                    return $announcement;
                }),
        ]);
    }

    public function join(JoinClassRequest $request, JoinClass $join): RedirectResponse
    {
        $section = $join($request->user(), $request->validated('join_code'));

        return redirect()->route('intern.class.show')->with('success', "Welcome to {$section->display_name}!");
    }

    public function people(Request $request): View|RedirectResponse
    {
        $section = $this->currentSection($request->user());

        if (! $section) {
            return redirect()->route('intern.class.show');
        }

        return view('intern.class.people', [
            'class' => $section,
            'profiles' => $section->internProfiles()->with('user')->get()
                ->sortBy(fn (InternProfile $profile) => mb_strtolower($profile->user->last_name.' '.$profile->user->first_name))
                ->values(),
        ]);
    }

    public function documents(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $section = $this->currentSection($user);

        if (! $section) {
            return redirect()->route('intern.class.show');
        }

        return view('intern.class.documents', [
            'class' => $section,
            'folders' => $section->folders()
                ->with(['submissions' => fn ($q) => $q->where('intern_id', $user->id)->latest()])
                ->orderBy('name')->get(),
            'resources' => $section->resources()->with('uploader')->latest()->get(),
        ]);
    }

    /** The intern's active class with adviser and intern count loaded, or null. */
    private function currentSection(User $user): ?ClassSection
    {
        return $user->internProfile?->classSection()->active()->with('adviser')->withCount('internProfiles')->first();
    }
}
