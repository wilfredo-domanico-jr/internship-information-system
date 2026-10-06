<?php

namespace App\Http\Controllers\Company;

use App\Actions\ApproveDtr;
use App\Actions\DisapproveDtr;
use App\Enums\DtrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\DisapproveDtrRequest;
use App\Models\Dtr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DtrController extends Controller
{
    public function index(Request $request): View
    {
        $group = DtrStatus::tryFrom((string) $request->query('status', 'pending')) ?? abort(404);
        $companyId = $request->user()->company->id;

        $base = Dtr::query()->whereHas('placement', fn ($q) => $q->where('company_id', $companyId));

        return view('company.dtrs.index', [
            'group' => $group,
            'counts' => collect(DtrStatus::cases())->mapWithKeys(fn (DtrStatus $s) => [$s->value => (clone $base)->where('status', $s)->count()])->all(),
            'dtrs' => (clone $base)->where('status', $group)
                ->with(['placement.intern.internProfile', 'placement.company', 'reviewer'])
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function approve(Request $request, Dtr $dtr, ApproveDtr $approve): RedirectResponse
    {
        Gate::authorize('review', $dtr);

        $approve($dtr, $request->user());

        return back()->with('success', "{$dtr->hours} hours credited to {$dtr->placement->intern->name}.");
    }

    public function disapprove(DisapproveDtrRequest $request, Dtr $dtr, DisapproveDtr $disapprove): RedirectResponse
    {
        $disapprove($dtr, $request->user(), $request->validated('note'));

        return back()->with('success', 'DTR disapproved. The intern has been notified.');
    }
}
