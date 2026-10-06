<?php

namespace App\Http\Controllers\Company;

use App\Actions\IssueCertificate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\IssueCertificateRequest;
use App\Models\Placement;
use App\Services\OjtHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request, OjtHoursService $hours): View
    {
        return view('company.certificates.index', [
            'placements' => Placement::query()->where('company_id', $request->user()->company->id)
                ->where('hours_rendered', '>=', $hours->certificateMinimum())
                ->with(['intern.internProfile', 'certificates' => fn ($q) => $q->latest('issued_at')])
                ->orderByDesc('hours_rendered')
                ->paginate(20),
            'hours' => $hours,
        ]);
    }

    public function store(IssueCertificateRequest $request, Placement $placement, IssueCertificate $issue): RedirectResponse
    {
        $issue($placement, $request->file('file'));

        return redirect()->route('company.certificates.index')->with('success', "Certificate issued to {$placement->intern->name}.");
    }
}
