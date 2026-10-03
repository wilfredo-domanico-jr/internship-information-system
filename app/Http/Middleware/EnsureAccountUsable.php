<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountUsable
{
    /**
     * Signs out accounts that were disabled mid-session and keeps unapproved
     * companies on the pending page until an admin verifies them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isDisabled()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been disabled. Please contact '.config('wiis.support.email').'.',
            ]);
        }

        if ($user && $user->isCompany() && ! $user->company?->isApproved() && ! $request->routeIs('account.pending', 'logout')) {
            return redirect()->route('account.pending');
        }

        return $next($request);
    }
}
