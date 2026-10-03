<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::withCount('placements')->orderBy('name')->get(),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::create(['name' => $request->validated('name')]);

        return back()->with('success', "{$department->name} was added.");
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update(['name' => $request->validated('name')]);

        return back()->with('success', "{$department->name} was renamed.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return back()->with('success', "{$department->name} was removed. Interns assigned to it are now unassigned.");
    }
}
