<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\LoadCurrentOrganization;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\ResetRequestCaches;
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
        $middleware->web(prepend: [RedirectIfNotInstalled::class], append: [EnsureAccountActive::class]);
        $middleware->alias([
            'org' => LoadCurrentOrganization::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);
        // Webhook Stripe : authentifié par signature, pas par jeton CSRF.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'sync/*') || $request->expectsJson(),
        );
    })->create();
