<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Déconnecte immédiatement un compte suspendu. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Compte suspendu.', 'code' => 'account_suspended', 'wipe' => true], 403);
            }

            return redirect()->route('login')->withErrors(['email' => 'Votre compte est suspendu. Contactez le support.']);
        }

        return $next($request);
    }
}
