<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterIntern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInternRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterInternController extends Controller
{
    public function create(): View
    {
        return view('auth.register-intern');
    }

    public function store(RegisterInternRequest $request, RegisterIntern $registerIntern): RedirectResponse
    {
        $user = $registerIntern($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('intern.dashboard')->with('success', 'Welcome to '.config('wiis.name').'! Your account is ready.');
    }
}
