<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateAdviser;
use App\Enums\AccountStatus;
use App\Enums\ClassStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdviserRequest;
use App\Models\InternProfile;
use App\Models\User;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdviserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = AccountStatus::tryFrom((string) $request->query('status'));

        $advisers = User::ofRole(Role::Adviser)
            ->withCount(['advisedClasses' => fn (Builder $c) => $c->where('status', ClassStatus::Active)])
            ->when($q !== '', fn (Builder $query) => Search::any($query, ['first_name', 'last_name', 'email', 'member_no'], $q))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(20);

        return view('admin.advisers.index', ['advisers' => $advisers]);
    }

    public function create(): View
    {
        return view('admin.advisers.create');
    }

    public function store(StoreAdviserRequest $request, CreateAdviser $createAdviser): RedirectResponse
    {
        $adviser = $createAdviser($request->validated());

        return redirect()->route('admin.advisers.show', $adviser)
            ->with('success', "{$adviser->name} was added. Sign-in details were emailed to {$adviser->email}.");
    }

    public function show(User $user): View
    {
        abort_unless($user->isAdviser(), 404);

        $classes = $user->advisedClasses()->withCount('internProfiles')->orderByDesc('school_year')->orderBy('section')->get();
        $interns = InternProfile::query()
            ->whereIn('class_section_id', $classes->pluck('id'))
            ->with(['user', 'classSection'])
            ->get()
            ->sortBy(fn (InternProfile $p) => $p->user->last_name);

        return view('admin.advisers.show', ['adviser' => $user, 'classes' => $classes, 'interns' => $interns]);
    }
}
