<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            // 1. Check active status
            if (! $user->is_active) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(403, 'Your staff account has been deactivated.');
            }

            // 2. Check session token version (prevents resurrection and enforces revocation)
            $sessionTokenVersion = $request->session()->get('token_version');
            if ($sessionTokenVersion !== null && (int) $sessionTokenVersion !== (int) $user->token_version) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(403, 'Your session has been revoked. Please log in again.');
            }

            // Sync token version into session if missing
            if ($sessionTokenVersion === null) {
                $request->session()->put('token_version', (int) $user->token_version);
            }
        }

        return $next($request);
    }
}
