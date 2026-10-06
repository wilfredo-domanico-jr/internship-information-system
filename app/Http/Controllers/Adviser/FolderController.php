<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\CreateFolder;
use App\Actions\DeleteFolder;
use App\Actions\ToggleFolderLock;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\FolderRequest;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FolderController extends Controller
{
    public const GROUPS = ['pending', 'approved', 'declined', 'late'];

    public function store(FolderRequest $request, ClassSection $classSection, CreateFolder $create): RedirectResponse
    {
        $folder = $create($classSection, $request->validated('name'));

        return redirect()->route('adviser.classes.documents', $classSection)->with('success', "Folder “{$folder->name}” created.");
    }

    public function show(Request $request, ClassFolder $folder): View
    {
        Gate::authorize('manage', $folder);

        $group = (string) $request->query('status', 'pending');
        abort_unless(in_array($group, self::GROUPS, true), 404);

        $base = ClassSubmission::query()->where('class_folder_id', $folder->id);

        $counts = [
            'pending' => (clone $base)->where('status', SubmissionStatus::Pending)->count(),
            'approved' => (clone $base)->where('status', SubmissionStatus::Approved)->count(),
            'declined' => (clone $base)->where('status', SubmissionStatus::Declined)->count(),
            'late' => (clone $base)->where('is_late', true)->count(),
        ];

        $submissions = $group === 'late'
            ? (clone $base)->where('is_late', true)
            : (clone $base)->where('status', SubmissionStatus::from($group));

        return view('adviser.folders.show', [
            'folder' => $folder->load('classSection'),
            'class' => $folder->classSection->loadCount('internProfiles'),
            'group' => $group,
            'counts' => $counts,
            'submissions' => $submissions->with(['intern.internProfile', 'reviewer'])->latest()->paginate(20)->withQueryString()
                ->through(function (ClassSubmission $submission) use ($folder) {
                    $submission->setRelation('folder', $folder);

                    return $submission;
                }),
        ]);
    }

    public function toggleLock(ClassFolder $folder, ToggleFolderLock $toggle): RedirectResponse
    {
        Gate::authorize('manage', $folder);

        $toggle($folder);

        return back()->with('success', $folder->is_locked
            ? "“{$folder->name}” is locked. New uploads will be marked late."
            : "“{$folder->name}” is open again.");
    }

    public function destroy(ClassFolder $folder, DeleteFolder $delete): RedirectResponse
    {
        Gate::authorize('manage', $folder);

        $section = $folder->classSection;
        $delete($folder);

        return redirect()->route('adviser.classes.documents', $section)->with('success', "Folder “{$folder->name}” and its submissions were deleted.");
    }
}
