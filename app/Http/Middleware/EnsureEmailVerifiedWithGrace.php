<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirmation de l'adresse e-mail sans bloquer l'arrivée : un compte neuf utilise l'application
 * tout de suite (bandeau de rappel), et la confirmation n'est exigée qu'après GRACE_DAYS jours.
 */
class EnsureEmailVerifiedWithGrace
{
    public const GRACE_DAYS = 7;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->hasVerifiedEmail() && $user->created_at?->lt(now()->subDays(self::GRACE_DAYS))) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Confirmez votre adresse e-mail pour continuer.'], 409)
                : redirect()->guest(route('verification.notice'));
        }

        return $next($request);
    }
}
