<?php

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;

/** Oriente vers l'installeur tant que l'application n'est pas installée, puis le ferme définitivement. */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next)
    {
        $installing = $request->is('install', 'install/*');
        if (! Installation::isInstalled()) {
            return $installing || $request->is('up') ? $next($request) : redirect('/install');
        }
        // Seule la page de fin (lien signé, valable quelques minutes) reste accessible après le verrouillage.
        abort_if($installing && ! ($request->is('install/termine') && $request->hasValidSignature()), 404);

        return $next($request);
    }
}
