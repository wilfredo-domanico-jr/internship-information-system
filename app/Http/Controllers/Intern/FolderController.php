<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\ClassFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FolderController extends Controller
{
    public function show(Request $request, ClassFolder $folder): View
    {
        Gate::authorize('view', $folder);

        return view('intern.folders.show', [
            'folder' => $folder->load('classSection'),
            'class' => $folder->classSection->loadCount('internProfiles'),
            'submissions' => $folder->submissions()->where('intern_id', $request->user()->id)->with('reviewer')->latest()->get(),
        ]);
    }
}
