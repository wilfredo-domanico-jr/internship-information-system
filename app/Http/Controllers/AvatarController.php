<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAvatarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    public function store(StoreAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        $old = $user->avatar_path;

        $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        $this->deleteExisting($old);

        return redirect()->route('profile.edit')->with('success', 'Profile photo updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->deleteExisting($user->avatar_path);
        $user->update(['avatar_path' => null]);

        return redirect()->route('profile.edit')->with('success', 'Profile photo removed.');
    }

    private function deleteExisting(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
