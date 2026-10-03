<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $approval = ApprovalStatus::tryFrom((string) $request->query('approval'));

        $companies = Company::registered()
            ->with('user')
            ->withCount(['activePlacements', 'postings'])
            ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $w) use ($q) {
                Search::any($w, ['name', 'company_code'], $q)
                    ->orWhereHas('user', fn (Builder $u) => Search::any($u, ['email', 'first_name', 'last_name'], $q));
            }))
            ->when($approval, fn (Builder $query) => $query->where('approval_status', $approval))
            ->orderBy('name')
            ->paginate(20);

        return view('admin.companies.index', [
            'companies' => $companies,
            'pendingCount' => Company::registered()->pending()->count(),
        ]);
    }

    public function show(Company $company): View
    {
        abort_unless($company->isRegistered(), 404);

        $company->load(['user', 'approver', 'activePlacements.intern', 'activePlacements.department'])
            ->loadCount(['postings', 'placements']);

        return view('admin.companies.show', ['company' => $company]);
    }
}
