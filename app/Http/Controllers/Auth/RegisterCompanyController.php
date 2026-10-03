<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterCompanyController extends Controller
{
    public function create(): View
    {
        return view('auth.register-company');
    }

    public function store(RegisterCompanyRequest $request, RegisterCompany $registerCompany): RedirectResponse
    {
        $user = $registerCompany(
            $request->safe()->except(['permit', 'moa', 'terms', 'password_confirmation']),
            $request->file('permit'),
            $request->file('moa'),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.pending')->with('success', 'Registration received. We will email you once your documents are verified.');
    }
}
