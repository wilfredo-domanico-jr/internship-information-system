<?php

namespace App\Http\Controllers\Company;

use App\Actions\AssignDepartment;
use App\Actions\RemoveIntern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\AssignDepartmentRequest;
use App\Models\Placement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PlacementController extends Controller
{
    public function department(AssignDepartmentRequest $request, Placement $placement, AssignDepartment $assign): RedirectResponse
    {
        $assign($placement, $request->validated('department_id') !== null ? (int) $request->validated('department_id') : null);

        return back()->with('success', "{$placement->intern->name}'s department was updated.");
    }

    public function remove(Placement $placement, RemoveIntern $remove): RedirectResponse
    {
        Gate::authorize('manage', $placement);

        $remove($placement);

        return redirect()->route('company.interns.index')->with('success', "{$placement->intern->name} was removed. Their record is in History.");
    }
}
