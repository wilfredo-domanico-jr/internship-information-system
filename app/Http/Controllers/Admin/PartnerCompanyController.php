<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreatePartnerCompany;
use App\Actions\DeletePartnerCompany;
use App\Actions\UpdatePartnerCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerCompanyRequest;
use App\Models\Company;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.partners.index', [
            'companies' => Company::partners()
                ->withCount(['activePlacements', 'cosApplications'])
                ->when($q !== '', fn (Builder $query) => Search::any($query, ['name', 'company_code'], $q))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.partners.create');
    }

    public function store(PartnerCompanyRequest $request, CreatePartnerCompany $create): RedirectResponse
    {
        $company = $create($request->safe()->except('logo'), $request->file('logo'));

        return redirect()->route('admin.partners.index')->with('success', "{$company->name} was added as a partner company.");
    }

    public function edit(Company $company): View
    {
        abort_unless($company->isPartner(), 404);

        return view('admin.partners.edit', ['company' => $company]);
    }

    public function update(PartnerCompanyRequest $request, Company $company, UpdatePartnerCompany $update): RedirectResponse
    {
        abort_unless($company->isPartner(), 404);

        $update($company, $request->safe()->except('logo'), $request->file('logo'));

        return redirect()->route('admin.partners.index')->with('success', "{$company->name} was updated.");
    }

    public function destroy(Company $company, DeletePartnerCompany $delete): RedirectResponse
    {
        abort_unless($company->isPartner(), 404);

        $delete($company);

        return redirect()->route('admin.partners.index')->with('success', "{$company->name} was removed.");
    }
}
