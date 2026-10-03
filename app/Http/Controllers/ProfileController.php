<?php

namespace App\Http\Controllers;

use App\Actions\UpdateProfile;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load(['internProfile.classSection', 'company']);

        return view('profile.edit', ['user' => $user]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        $updateProfile($request->user(), $request->validated());

        return redirect()->route('profile.edit')->with('success', 'Profile updated.');
    }
}
