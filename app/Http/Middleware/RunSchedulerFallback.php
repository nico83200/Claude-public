<?php

namespace App\Http\Middleware;

use App\Services\NotificationDiagnostics;
use App\Services\SystemMonitor;
use Closure;
use Illuminate\Http\Request;

/** Relais du planificateur quand le cron n'est pas configuré (après l'envoi de la réponse). */
class RunSchedulerFallback
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (app()->runningUnitTests() && ! config('equine.scheduler_fallback_in_tests')) {
            return;
        }
        NotificationDiagnostics::runFallbackIfNeeded();
        // Cron arrêté : la surveillance est aussi vérifiée depuis les visites (au plus toutes les 10 minutes).
        if (! NotificationDiagnostics::cronAlive()) {
            SystemMonitor::checkFromWeb();
        }
    }
}
