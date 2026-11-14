<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retour au même endroit : une modification ou une suppression faite depuis une fiche, ouverte depuis une liste,
 * ramène à cette liste (champ `_return` ajouté par return-position.js) ; la ligne y est retrouvée et surlignée.
 * Seulement une adresse de l'appli, seulement après un enregistrement réussi (saisie refusée ou conflit : on reste sur le formulaire).
 */
class ReturnToOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $target = (string) $request->input('_return', '');
        if (! in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true) || ! $response instanceof RedirectResponse
            || ! self::isInternal($target) || ($request->hasSession() && array_intersect(['errors', 'error'], $request->session()->get('_flash.new', [])))) {
            return $response;
        }

        return $response->setTargetUrl(url($target));
    }

    /** Chemin de l'appli (« /budget?horse=3 »), jamais une autre adresse (« //exemple.com », « https://… », « /\\… »). */
    public static function isInternal(string $target): bool
    {
        return $target !== '' && str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! str_contains($target, '\\')
            && ! preg_match('/[\x00-\x1F]/', $target) && strlen($target) <= 500;
    }
}
