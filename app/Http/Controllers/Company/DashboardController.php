<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CompanyDashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CompanyDashboardStats $stats): View
    {
        $company = $request->user()->company;

        return view('company.dashboard', [
            'company' => $company,
            'stats' => $stats->counts($company),
            'buckets' => $stats->hourBuckets($company),
        ]);
    }
}
