<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Placement;
use App\Services\OjtHoursService;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternController extends Controller
{
    public function index(Request $request, OjtHoursService $hours): View
    {
        $q = trim((string) $request->query('q'));

        return view('company.interns.index', [
            'placements' => Placement::active()->where('company_id', $request->user()->company->id)
                ->with(['intern.internProfile.classSection', 'department'])
                ->when($q !== '', fn (Builder $query) => $query->whereHas('intern', fn (Builder $u) => Search::any($u, ['first_name', 'last_name', 'email'], $q)))
                ->orderBy('started_at')
                ->paginate(20)->withQueryString(),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'hours' => $hours,
        ]);
    }
}
