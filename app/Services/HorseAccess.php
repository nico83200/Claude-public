<?php

namespace App\Services;

use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseOrganizationAssignment;
use App\Models\HorseOwnership;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    /** @var array<int, array<int, true>> chevaux dont l'utilisateur est propriétaire, par utilisateur */
    private array $owned = [];

    /** @var array<int, array<int, string[]>> permissions des partages directs, par utilisateur puis cheval */
    private array $granted = [];

    /** @var array<int, Collection> rattachements actifs à une écurie, par cheval */
    private array $assignments = [];

    /** @var array<int, Collection> chevaux accessibles, par utilisateur */
    private array $accessible = [];

    /** @var array<string, int[]> identifiants des chevaux par utilisateur et permission */
    private array $idsWith = [];

    public function __construct(private Entitlements $entitlements) {}

    /** Oublie tout ce qui a été mémorisé (après un changement de droits dans la même requête). */
    public function flush(): void
    {
        $this->cache = [];
        $this->memberships = [];
        $this->owned = [];
        $this->granted = [];
        $this->assignments = [];
        $this->accessible = [];
        $this->idsWith = [];
    }

    /** Une requête par utilisateur (et non par cheval) pour la propriété et les partages directs. */
    private function ownedBy(User $user): array
    {
        return $this->owned[$user->id] ??= HorseOwnership::where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', now()->toDateString()))
            ->whereIn('role', ['owner', 'co_owner'])->pluck('horse_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    private function grantsOf(User $user): array
    {
        return $this->granted[$user->id] ??= HorseAccessGrant::active()->where('user_id', $user->id)->get()
            ->groupBy('horse_id')->map(fn ($g) => $g->flatMap(fn ($grant) => $grant->permissions ?? [])->unique()->values()->all())->all();
    }

    /** Rattachements des chevaux donnés, chargés en une requête pour ceux qui ne le sont pas encore. */
    private function loadAssignments(array $horseIds): void
    {
        $missing = array_values(array_diff($horseIds, array_keys($this->assignments)));
        if (! $missing) {
            return;
        }
        $found = HorseOrganizationAssignment::active()->whereIn('horse_id', $missing)->get()->groupBy('horse_id');
        foreach ($missing as $id) {
            $this->assignments[$id] = $found->get($id, collect());
        }
    }

    private function membershipsOf(User $user)
    {
        // Adhésions déjà lues par le middleware de l'espace courant : réutilisées plutôt que relues.
        return $this->memberships[$user->id] ??= ($user->relationLoaded('memberships')
            ? $user->memberships->loadMissing('role.permissions')
            : $user->memberships()->with('role.permissions')->get())->keyBy('organization_id');
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
        if (isset($this->ownedBy($user)[$horse->id])) {
            $perms = array_merge($perms, Perm::allHorseKeys());
        }

        // 3. Partage direct
        $perms = array_merge($perms, array_intersect($this->grantsOf($user)[$horse->id] ?? [], Perm::horseKeys()));

        // 4. Écurie de rattachement
        $this->loadAssignments([$horse->id]);
        foreach ($this->assignments[$horse->id] as $assignment) {
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

        $orgIds = $user->relationLoaded('memberships') ? $user->memberships->pluck('organization_id') : $user->memberships()->pluck('organization_id');
        $userId = $user->id;
        $today = now()->toDateString();
        $now = now();

        return Horse::query()->where(function (Builder $q) use ($orgIds, $userId, $today, $now) {
            $q->whereIn('organization_id', $orgIds)
                ->orWhereIn('id', fn ($s) => $s->select('horse_id')->from('horse_ownerships')->where('user_id', $userId)
                    ->whereIn('role', ['owner', 'co_owner'])
                    ->where(fn ($w) => $w->whereNull('ended_on')->orWhere('ended_on', '>=', $today)))
                ->orWhereIn('id', fn ($s) => $s->select('horse_id')->from('horse_access_grants')->where('user_id', $userId)
                    ->whereNull('revoked_at')->whereNull('paused_at')
                    ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now)))
                ->orWhereIn('id', fn ($s) => $s->select('horse_id')->from('horse_organization_assignments')
                    ->whereIn('organization_id', $orgIds)->where('status', 'active')
                    ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', $now)));
        });
    }

    /**
     * Prépare une liste de chevaux avant d'en vérifier les droits un par un :
     * rattachements et organisations lus en une requête chacun (au lieu d'une par cheval).
     */
    public function preload(EloquentCollection $horses): EloquentCollection
    {
        $this->loadAssignments($horses->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $horses->loadMissing('organization');
    }

    /** Identifiants des chevaux pour lesquels l'utilisateur détient une permission donnée. */
    public function horseIdsWith(User $user, string $permission): array
    {
        return $this->idsWith[$user->id.':'.$permission] ??= $this->accessibleHorses($user)
            ->filter(fn (Horse $h) => in_array($permission, $this->permissions($user, $h), true))
            ->pluck('id')->values()->all();
    }

    /** Chevaux accessibles, lus une fois par requête, rattachements préchargés en une requête. */
    public function accessibleHorses(User $user): Collection
    {
        if (! isset($this->accessible[$user->id])) {
            $horses = $this->accessibleQuery($user)->get();
            $this->loadAssignments($horses->pluck('id')->map(fn ($id) => (int) $id)->all());
            $this->accessible[$user->id] = $horses;
        }

        return $this->accessible[$user->id];
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
        // Mêmes lectures que pour les droits par cheval (déjà en mémoire sur la plupart des pages).
        if ($this->ownedBy($user)) {
            $perms = array_merge($perms, Perm::allHorseKeys());
        }
        foreach ($this->grantsOf($user) as $p) {
            $perms = array_merge($perms, $p);
        }

        return array_values(array_unique($perms));
    }
}
