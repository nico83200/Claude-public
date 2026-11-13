<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Horse;
use App\Services\Audit;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Historique des actions (Administration › Journal) : chaque ajout, modification ou suppression réussi d'une personne
 * connectée est noté — l'action (nom de la page), la personne, l'élément concerné, l'adresse IP — jamais le contenu
 * saisi. Une action qui a déjà sa ligne détaillée (Audit::log) n'est pas notée deux fois.
 */
class RecordUserActions
{
    /** Gestes techniques ou sans intérêt pour l'historique. */
    private const SKIPPED = ['push.', 'focus.set', 'notifications.', 'install', 'stripe.', 'login', 'logout', 'register', 'heartbeat', 'maintenance.', 'digest.',
        'videos.seen', 'onboarding.', 'settings.theme', 'organizations.switch', 'storage.local.upload', 'two-factor.login', 'password.confirm'];

    public function handle(Request $request, Closure $next): Response
    {
        Audit::$written = false;
        $response = $next($request);
        try {
            $this->record($request, $response);
        } catch (Throwable $e) {
            report($e); // l'historique ne doit jamais faire échouer la saisie
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        $name = $request->route()?->getName();
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) || ! $name || Audit::$written
            || $response->getStatusCode() >= 400 || ! $request->user() || collect(self::SKIPPED)->contains(fn ($p) => str_starts_with($name, $p))) {
            return;
        }
        // Formulaire refusé (erreurs de saisie) : rien n'a été fait.
        if ($request->hasSession() && $request->session()->has('errors')) {
            return;
        }
        $models = collect($request->route()->parameters())->filter(fn ($v) => $v instanceof Model);
        $subject = $models->last();
        $horse = $models->first(fn ($m) => $m instanceof Horse);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'organization_id' => $subject?->organization_id ?? $horse?->organization_id,
            'action' => $name,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => ['auto' => true] + ($horse && $horse !== $subject ? ['horse_id' => $horse->id] : []),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 250) ?: null,
        ]);
    }
}
