<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Déconnecte immédiatement un compte suspendu, et note la dernière visite
 * (rappel après une absence, au plus une écriture toutes les 5 minutes).
 */
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

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5)))) {
            DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
            $user->setRawAttributes(['last_seen_at' => now()] + $user->getAttributes(), true);
        }

        return $next($request);
    }
}
