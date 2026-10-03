<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email']]);

        // Always respond the same way so the form cannot be used to enumerate accounts.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If that email is registered, a password reset link is on its way.');
    }
}
