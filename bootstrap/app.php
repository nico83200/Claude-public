<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\LoadCurrentOrganization;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\ResetRequestCaches;
use App\Http\Middleware\RunSchedulerFallback;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResetRequestCaches::class);
        // Ancienne adresse (jackcie.nlapps.fr) → adresse actuelle, avant toute session ou maintenance.
        $middleware->prepend(\App\Http\Middleware\RedirectLegacyHost::class);
        $middleware->web(prepend: [RedirectIfNotInstalled::class], append: [EnsureAccountActive::class, RunSchedulerFallback::class]);
        $middleware->alias([
            'org' => LoadCurrentOrganization::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);
        // Webhook Stripe : authentifié par signature, pas par jeton CSRF.
        $middleware->validateCsrfTokens(except: ['stripe/webhook', 'connexion/apple/retour', 'push/recu']);
        // Reprise d'une mise à jour restée en maintenance : accessible pendant la maintenance.
        $middleware->preventRequestsDuringMaintenance(except: ['maintenance/reprise']);
        // Cookie de contournement de la maintenance (mise à jour depuis l'administration) : lu en clair par le mode maintenance.
        $middleware->encryptCookies(except: ['laravel_maintenance']);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Surveillance : compteur d'erreurs pour l'alerte « erreurs en hausse » (le journal habituel reste écrit).
        $exceptions->report(fn (Throwable $e) => \App\Services\SystemMonitor::recordError($e));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
