<?php

namespace App\Http\Controllers\Intern;

use App\Actions\DeleteDtr;
use App\Actions\SubmitDtr;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\SubmitDtrRequest;
use App\Models\Dtr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DtrController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('intern.dtrs.index', [
            'placement' => $user->activePlacement()->with('company')->first(),
            'dtrs' => Dtr::query()->whereHas('placement', fn ($q) => $q->where('intern_id', $user->id))
                ->with(['placement.company', 'reviewer'])
                ->latest('period_to')->latest()
                ->paginate(20),
        ]);
    }

    public function store(SubmitDtrRequest $request, SubmitDtr $submit): RedirectResponse
    {
        $submit($request->user(), $request->safe()->except('file'), $request->file('file'));

        return redirect()->route('intern.dtrs.index')->with('success', 'DTR submitted. Your company will review it.');
    }

    public function destroy(Dtr $dtr, DeleteDtr $delete): RedirectResponse
    {
        Gate::authorize('delete', $dtr);

        $delete($dtr);

        return redirect()->route('intern.dtrs.index')->with('success', 'DTR withdrawn.');
    }
}
