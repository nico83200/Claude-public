<?php

namespace App\Http\Middleware;

use App\Services\Entitlements;
use App\Services\HorseAccess;
use Closure;
use Illuminate\Http\Request;

class ResetRequestCaches
{
    public function handle(Request $request, Closure $next)
    {
        app(HorseAccess::class)->flush();
        app(Entitlements::class)->flush();

        return $next($request);
    }
}
