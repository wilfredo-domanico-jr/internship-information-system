<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminDashboardStats;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardStats $stats): View
    {
        $byRole = $stats->accountStatusByRole();

        return view('admin.dashboard', [
            'stats' => $stats->counts(),
            'placement' => $stats->placementSplit(),
            'sectioning' => $stats->sectioningSplit(),
            'statusLabels' => array_keys($byRole),
            'statusActive' => array_column($byRole, 'active'),
            'statusDisabled' => array_column($byRole, 'disabled'),
            'recentUsers' => User::latest()->limit(6)->get(),
        ]);
    }
}
