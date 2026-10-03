<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChangePasswordController extends Controller
{
    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();
        Auth::logoutOtherDevices($request->validated('password'));

        return back()->with('success', 'Your password has been updated.');
    }
}
