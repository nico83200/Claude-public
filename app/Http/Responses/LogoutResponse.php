<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Après la déconnexion : retour sur le site vitrine (jackcie.fr).
 * Dans l'application installée sur le téléphone, retour à la page de connexion de l'application :
 * quitter vers le site ouvrirait le navigateur, et sur iPhone la reconnexion ne se ferait plus dans l'application.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }
        if ($request->boolean('pwa') || ! filled(config('equine.site_url'))) {
            return redirect()->route('login');
        }

        return redirect()->away((string) config('equine.site_url'));
    }
}
