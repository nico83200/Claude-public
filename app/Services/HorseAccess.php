<?php

namespace App\Services;

use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseOrganizationAssignment;
use App\Models\HorseOwnership;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Point unique de résolution des droits sur un dossier de cheval.
 *
 * Un utilisateur obtient des permissions sur un cheval par :
 *  1. son rôle dans l'organisation gestionnaire du cheval ;
 *  2. une propriété active (horse_ownerships) — accès complet ;
 *  3. un partage direct actif (horse_access_grants) — droits explicites ;
 *  4. son rôle dans une écurie à laquelle le cheval est rattaché — intersection
 *     entre les droits accordés à l'écurie par le propriétaire et son rôle.
 *
 * Le super-administrateur n'obtient AUCUN accès implicite aux données métier.
 */
class HorseAccess
{
    /** @var array<string, array> */
    private array $cache = [];

    /** @var array<int, Collection> */
    private array $memberships = [];

    public function __construct(private Entitlements $entitlements) {}

    public function flush(): void
    {
        $this->cache = [];
        $this->memberships = [];
    }

    private function membershipsOf(User $user)
    {
        return $this->memberships[$user->id] ??= $user->memberships()->with('role.permissions')->get()->keyBy('organization_id');
    }

    public function permissions(User $user, Horse $horse): array
    {
        $key = $user->id.':'.$horse->id;

        return $this->cache[$key] ??= $this->resolve($user, $horse);
    }

    private function resolve(User $user, Horse $horse): array
    {
        if ($user->isSuspended() || $horse->trashed()) {
            return [];
        }

        $perms = [];

        // 1. Organisation gestionnaire
        $member = $this->membershipsOf($user)->get($horse->organization_id);
        if ($member) {
            $rolePerms = $member->role->permissionKeys();
            $perms = array_merge($perms, array_intersect($rolePerms, Perm::horseKeys()));
            if (in_array($member->role->key, Perm::MANAGING_ROLES, true) && $member->role->is_system) {
                $perms[] = Perm::HORSE_MANAGE;
            }
        }

        // 2. Propriétaire déclaré avec un compte
        $isOwner = HorseOwnership::where('horse_id', $horse->id)->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', now()->toDateString()))
            ->whereIn('role', ['owner', 'co_owner'])->exists();
        if ($isOwner) {
            $perms = array_merge($perms, Perm::allHorseKeys());
        }

        // 3. Partage direct
        foreach (HorseAccessGrant::active()->where('horse_id', $horse->id)->where('user_id', $user->id)->get() as $grant) {
            $perms = array_merge($perms, array_intersect($grant->permissions ?? [], Perm::horseKeys()));
        }

        // 4. Écurie de rattachement
        $assignments = HorseOrganizationAssignment::active()->where('horse_id', $horse->id)->get();
        foreach ($assignments as $assignment) {
            $m = $this->membershipsOf($user)->get($assignment->organization_id);
            if ($m) {
                $perms = array_merge($perms, array_intersect($assignment->permissions ?? [], $m->role->permissionKeys(), Perm::horseKeys()));
            }
        }

        $perms = array_values(array_unique($perms));

        // Toute permission implique la consultation de la fiche.
        if ($perms && ! in_array(Perm::HORSE_VIEW, $perms, true)) {
            $perms[] = Perm::HORSE_VIEW;
        }
        // Les traitements sont visibles par ceux qui voient les soins.
        if (in_array(Perm::HEALTH_VIEW, $perms, true) && ! in_array(Perm::TREATMENTS_VIEW, $perms, true)) {
            $perms[] = Perm::TREATMENTS_VIEW;
        }

        return $perms;
    }

    /** Vérifie une permission, y compris l'état de la licence pour les écritures. */
    public function can(User $user, Horse $horse, string $permission): bool
    {
        if (! in_array($permission, $this->permissions($user, $horse), true)) {
            return false;
        }
        if (Perm::isRead($permission)) {
            return true;
        }
        if ($horse->isArchived() && $permission !== Perm::HORSE_MANAGE) {
            return false;
        }

        return $this->entitlements->writable($horse->organization);
    }

    /** Raison lisible d'un refus (pour l'interface). */
    public function denialReason(User $user, Horse $horse, string $permission): string
    {
        if (in_array($permission, $this->permissions($user, $horse), true) && ! Perm::isRead($permission)) {
            if ($horse->isArchived()) {
                return 'Ce cheval est archivé : son dossier est en lecture seule.';
            }

            return $this->entitlements->for($horse->organization)['read_only_reason'] ?? 'Action non autorisée.';
        }

        return 'Vous n\'avez pas l\'autorisation d\'effectuer cette action sur ce cheval.';
    }

    /** Requête des chevaux accessibles (au moins en consultation). */
    public function accessibleQuery(User $user): Builder
    {
        if ($user->isSuspended()) {
            return Horse::query()->whereRaw('1 = 0');
        }

        $orgIds = $user->memberships()->pluck('organization_id');
        $userId = $user->id;
        $today = now()->toDateString();
        $now = now();

        return Horse::query()->where(function (Builder $q) use ($orgIds, $userId, $today, $now) {
            $q->whereIn('organization_id', $orgIds)
                ->orWhereIn('id', fn ($s) => $s->select('horse_id')->from('horse_ownerships')->where('user_id', $userId)
                    ->whereIn('role', ['owner', 'co_owner'])
                    ->where(fn ($w) => $w->whereNull('ended_on')->orWhere('ended_on', '>=', $today)))
                ->orWhereIn('id', fn ($s) => $s->select('horse_id')->from('horse_access_grants')->where('user_id', $userId)
                    ->whereNull('revoked_at')
                    ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now)))
                ->orWhereIn('id', fn ($s) => $s->select('horse_id')->from('horse_organization_assignments')
                    ->whereIn('organization_id', $orgIds)->where('status', 'active')
                    ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', $now)));
        });
    }

    /** Identifiants des chevaux pour lesquels l'utilisateur détient une permission donnée. */
    public function horseIdsWith(User $user, string $permission): array
    {
        return $this->accessibleQuery($user)->get()
            ->filter(fn (Horse $h) => in_array($permission, $this->permissions($user, $h), true))
            ->pluck('id')->all();
    }

    /**
     * Union des permissions cheval de l'utilisateur, tous chevaux confondus,
     * calculée en quelques requêtes (sert à adapter la navigation).
     */
    public function navPermissions(User $user): array
    {
        $perms = [];
        foreach ($this->membershipsOf($user) as $member) {
            $perms = array_merge($perms, $member->role->permissionKeys());
            if (in_array($member->role->key, Perm::MANAGING_ROLES, true) && $member->role->is_system) {
                $perms[] = Perm::HORSE_MANAGE;
            }
        }
        if (HorseOwnership::where('user_id', $user->id)->whereIn('role', ['owner', 'co_owner'])->exists()) {
            $perms = array_merge($perms, Perm::allHorseKeys());
        }
        foreach (HorseAccessGrant::active()->where('user_id', $user->id)->pluck('permissions') as $p) {
            $perms = array_merge($perms, $p ?? []);
        }

        return array_values(array_unique($perms));
    }
}
