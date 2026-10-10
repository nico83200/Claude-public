<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\Organization;
use App\Services\Entitlements;
use App\Services\HorseAccess;
use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    /** Autorise une action sur un cheval (permissions + état de la licence). */
    protected function authorizeHorse(Horse $horse, string $permission): void
    {
        Gate::authorize('horse', [$horse, $permission]);
    }

    protected function horseCan(Horse $horse, string $permission): bool
    {
        return app(HorseAccess::class)->can(request()->user(), $horse, $permission);
    }

    protected function horsePerms(Horse $horse): array
    {
        return app(HorseAccess::class)->permissions(request()->user(), $horse);
    }

    protected function authorizeOrg(Organization $organization, string $permission): void
    {
        Gate::authorize('org', [$organization, $permission]);
    }

    /** Espace courant (personnel ou écurie). */
    protected function currentOrganization(): Organization
    {
        $org = request()->attributes->get('organization');
        abort_unless($org instanceof Organization, 403, 'Aucun espace actif.');

        return $org;
    }

    protected function entitlements(): Entitlements
    {
        return app(Entitlements::class);
    }

    /** Vérifie qu'une fonctionnalité de l'offre est incluse. */
    protected function requireFeature(Organization $organization, string $feature, string $label): void
    {
        if (! $this->entitlements()->hasFeature($organization, $feature)) {
            abort(redirect()->back()->with('warning', "« {$label} » n'est pas inclus dans l'offre actuelle de l'espace {$organization->name}."));
        }
    }
}
