<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('company.history.index', [
            'placements' => Placement::query()->where('company_id', $request->user()->company->id)->whereNotNull('ended_at')
                ->with(['intern.internProfile', 'department'])->withCount('certificates')
                ->orderByDesc('ended_at')
                ->paginate(20),
        ]);
    }
}
