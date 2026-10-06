<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('intern.certificates.index', [
            'certificates' => Certificate::query()->whereHas('placement', fn ($q) => $q->where('intern_id', $request->user()->id))
                ->with('placement.company')->latest('issued_at')->get(),
        ]);
    }
}
