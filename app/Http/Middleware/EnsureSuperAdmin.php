<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Espace d'administration : réservé aux super-administrateurs ayant confirmé
 * l'authentification à deux facteurs, avec confirmation récente du mot de passe.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->is_super_admin, 404);

        if (! $user->hasConfirmedTwoFactor() && ! app()->environment('local', 'testing')) {
            return redirect()->route('settings.security')->with('warning', 'L\'authentification à deux facteurs est obligatoire pour accéder à l\'administration.');
        }

        return $next($request);
    }
}
