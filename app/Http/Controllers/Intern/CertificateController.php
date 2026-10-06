<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\OjtHoursService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __invoke(Request $request, OjtHoursService $hours): View
    {
        return view('intern.certificates.index', [
            'minimum' => $hours->certificateMinimum(),
            'certificates' => Certificate::query()->whereHas('placement', fn ($q) => $q->where('intern_id', $request->user()->id))
                ->with('placement.company')->latest('issued_at')->get(),
        ]);
    }
}
