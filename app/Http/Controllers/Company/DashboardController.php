<?php

namespace App\Http\Controllers\Company;

use App\Enums\DtrStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Dtr;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = $request->user()->company;
        $placementIds = $company->placements()->select('id');

        return view('company.dashboard', [
            'company' => $company,
            'stats' => [
                'active_interns' => $company->activePlacements()->count(),
                'open_postings' => $company->postings()->open()->count(),
                'pending_dtrs' => Dtr::whereIn('placement_id', $placementIds)->where('status', DtrStatus::Pending)->count(),
                'certificates' => Certificate::whereIn('placement_id', $placementIds)->count(),
            ],
        ]);
    }
}
