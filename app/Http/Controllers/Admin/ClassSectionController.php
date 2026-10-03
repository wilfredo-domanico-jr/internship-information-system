<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassSectionController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = ClassStatus::tryFrom((string) $request->query('status'));
        $year = (string) $request->query('year');

        $classes = ClassSection::query()
            ->with('adviser')
            ->withCount('internProfiles')
            ->when($q !== '', fn (Builder $query) => Search::any($query, ['course_code', 'subject', 'section', 'join_code'], $q))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($year !== '', fn (Builder $query) => $query->where('school_year', $year))
            ->orderByDesc('school_year')->orderBy('course_code')->orderBy('section')
            ->paginate(20);

        return view('admin.classes.index', [
            'classes' => $classes,
            'years' => ClassSection::query()->distinct()->orderByDesc('school_year')->pluck('school_year'),
        ]);
    }

    public function show(ClassSection $classSection): View
    {
        $classSection->load(['adviser', 'internProfiles.user'])->loadCount(['folders', 'announcements']);

        return view('admin.classes.show', [
            'class' => $classSection,
            'profiles' => $classSection->internProfiles->sortBy(fn ($p) => $p->user->last_name)->values(),
        ]);
    }
}
