<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ancienne adresse de l'application (ex. jackcie.nlapps.fr) : redirection permanente vers l'adresse actuelle (APP_URL),
 * même chemin et mêmes paramètres. Les QR codes déjà imprimés (fiches d'urgence, affiches), les liens de partage
 * et les agendas abonnés continuent ainsi de fonctionner.
 *
 * Le webhook Stripe est traité sur place : Stripe ne suit pas les redirections.
 */
class RedirectLegacyHost
{
    private const EXCEPT = ['stripe/webhook'];

    public function handle(Request $request, Closure $next): Response
    {
        $legacy = array_filter(array_map(fn ($h) => strtolower(trim($h)), explode(',', (string) config('app.legacy_hosts'))));
        $target = parse_url((string) config('app.url'));
        $host = strtolower($request->getHost());
        if (! $legacy || ! in_array($host, $legacy, true) || empty($target['host']) || strtolower($target['host']) === $host || $request->is(...self::EXCEPT)) {
            return $next($request);
        }
        $url = ($target['scheme'] ?? 'https').'://'.$target['host'].(isset($target['port']) ? ':'.$target['port'] : '').$request->getRequestUri();

        // 308 garde la méthode et le contenu d'un envoi de formulaire ; 301 pour une simple consultation.
        return redirect()->away($url, $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308)
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
