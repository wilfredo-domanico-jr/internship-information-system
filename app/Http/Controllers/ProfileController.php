<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load(['internProfile.classSection', 'company']);

        return view('profile.edit', ['user' => $user]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
            ]);

            if ($user->isIntern()) {
                $user->internProfile?->update([
                    'gender' => $data['gender'] ?? null,
                    'birthdate' => $data['birthdate'] ?? null,
                    'present_address' => $data['present_address'] ?? null,
                    'permanent_address' => $data['permanent_address'] ?? null,
                    'about' => $data['about'] ?? null,
                ]);
            }

            if ($user->isCompany()) {
                $user->company?->update([
                    'name' => $data['company_name'],
                    'type' => $data['company_type'],
                    'website' => $data['website'] ?? null,
                    'address' => $data['address'],
                    'about' => $data['about'] ?? null,
                ]);
            }
        });

        return redirect()->route('profile.edit')->with('success', 'Profile updated.');
    }
}
