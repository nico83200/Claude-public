<?php

namespace App\Http\Middleware;

use App\Services\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Détermine l'espace courant (personnel ou écurie) et le partage avec les vues.
 */
class LoadCurrentOrganization
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $memberships = $user->memberships()->with(['organization', 'role'])->get();
            $user->setRelation('memberships', $memberships);
            $current = $memberships->firstWhere('organization_id', $user->current_organization_id)?->organization
                ?? $memberships->first()?->organization;

            if ($current && $current->id !== $user->current_organization_id) {
                $user->forceFill(['current_organization_id' => $current->id])->saveQuietly();
            }
            $request->attributes->set('organization', $current);
            View::share('currentOrganization', $current);
            View::share('currentEntitlements', $current ? app(Entitlements::class)->for($current) : null);
            View::share('userMemberships', $memberships);
        }

        return $next($request);
    }
}
