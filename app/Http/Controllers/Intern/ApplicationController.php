<?php

namespace App\Http\Controllers\Intern;

use App\Actions\ApplyToPosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\ApplyRequest;
use App\Models\InternshipPosting;
use Illuminate\Http\RedirectResponse;

class ApplicationController extends Controller
{
    public function store(ApplyRequest $request, InternshipPosting $posting, ApplyToPosting $apply): RedirectResponse
    {
        $apply($request->user(), $posting, $request->file('resume'), $request->file('endorsement'));

        return redirect()->route('intern.postings.show', $posting)->with('success', "Application sent to {$posting->company->name}. You will be notified about the next step.");
    }
}
