<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'interns' => User::ofRole(Role::Intern)->active()->count(),
                'advisers' => User::ofRole(Role::Adviser)->active()->count(),
                'companies' => Company::registered()->approved()->count(),
                'pending_companies' => Company::registered()->pending()->count(),
            ],
            'recentUsers' => User::latest()->limit(6)->get(),
        ]);
    }
}
