<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DisableUser;
use App\Actions\ReactivateUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserStatusController extends Controller
{
    public function disable(Request $request, User $user, DisableUser $disable): RedirectResponse
    {
        $disable($user, $request->user());

        return back()->with('success', "{$user->name} has been disabled and signed out.");
    }

    public function reactivate(User $user, ReactivateUser $reactivate): RedirectResponse
    {
        $reactivate($user);

        return back()->with('success', "{$user->name} has been reactivated.");
    }
}
