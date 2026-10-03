<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveCompany;
use App\Actions\RejectCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyApprovalController extends Controller
{
    public function index(): View
    {
        return view('admin.companies.pending', [
            'companies' => Company::registered()->pending()->with('user')->oldest()->paginate(20),
            'pendingCount' => Company::registered()->pending()->count(),
        ]);
    }

    public function approve(Request $request, Company $company, ApproveCompany $approve): RedirectResponse
    {
        abort_unless($company->isRegistered(), 404);

        $changed = $approve($company, $request->user());

        return back()->with(
            $changed ? 'success' : 'info',
            $changed ? "{$company->name} is now verified. The company has been notified." : "{$company->name} was already approved."
        );
    }

    public function reject(RejectCompanyRequest $request, Company $company, RejectCompany $reject): RedirectResponse
    {
        abort_unless($company->isRegistered(), 404);

        $changed = $reject($company, $request->user(), $request->validated('reason'));

        return back()->with(
            $changed ? 'success' : 'info',
            $changed ? "{$company->name} was rejected. The company has been notified." : "{$company->name} was already rejected."
        );
    }
}
