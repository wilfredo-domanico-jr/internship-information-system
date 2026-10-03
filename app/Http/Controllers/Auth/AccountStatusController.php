<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    public function pending(Request $request): View|RedirectResponse
    {
        $company = $request->user()->company;

        if (! $request->user()->isCompany() || $company?->isApproved()) {
            return redirect()->route('dashboard');
        }

        return view('account.pending', ['company' => $company]);
    }
}
