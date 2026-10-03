<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\User;
use App\Services\OjtHoursService;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = AccountStatus::tryFrom((string) $request->query('status'));
        $placement = $request->query('placement');

        $interns = User::ofRole(Role::Intern)
            ->with(['internProfile.classSection', 'activePlacement.company'])
            ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $w) use ($q) {
                Search::any($w, ['first_name', 'last_name', 'email', 'member_no'], $q)
                    ->orWhereHas('internProfile', fn (Builder $p) => Search::like($p, 'student_number', $q));
            }))
            ->when($request->integer('class'), fn (Builder $query, int $class) => $query->whereHas('internProfile', fn (Builder $p) => $p->where('class_section_id', $class)))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($placement === 'placed', fn (Builder $query) => $query->whereHas('activePlacement'))
            ->when($placement === 'unplaced', fn (Builder $query) => $query->whereDoesntHave('activePlacement'))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(20);

        return view('admin.interns.index', [
            'interns' => $interns,
            'classes' => ClassSection::active()->orderBy('course_code')->orderBy('section')->get(['id', 'course_code', 'section']),
        ]);
    }

    public function show(User $user, OjtHoursService $hours): View
    {
        abort_unless($user->isIntern(), 404);

        $user->load(['internProfile.classSection.adviser', 'activePlacement.company', 'placements.company']);
        $total = $user->internProfile?->total_hours ?? 0;

        return view('admin.interns.show', [
            'intern' => $user,
            'profile' => $user->internProfile,
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
