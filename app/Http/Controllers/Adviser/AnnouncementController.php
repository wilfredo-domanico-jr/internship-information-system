<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\PostAnnouncement;
use App\Actions\UpdateAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\ClassSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function store(AnnouncementRequest $request, ClassSection $classSection, PostAnnouncement $post): RedirectResponse
    {
        $post($classSection, $request->user(), $request->validated('body'));

        return redirect()->route('adviser.classes.show', $classSection)->with('success', 'Announcement posted.');
    }

    public function edit(Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);

        return view('adviser.announcements.edit', ['announcement' => $announcement, 'class' => $announcement->classSection]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement, UpdateAnnouncement $update): RedirectResponse
    {
        $update($announcement, $request->validated('body'));

        return redirect()->route('adviser.classes.show', $announcement->classSection)->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $section = $announcement->classSection;
        $announcement->delete();

        return redirect()->route('adviser.classes.show', $section)->with('success', 'Announcement deleted.');
    }
}
