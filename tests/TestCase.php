<?php

namespace Tests;

use App\Models\Horse;
use App\Models\License;
use App\Models\Organization;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\HorseAccess;
use App\Services\HorseService;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Données de référence (permissions, rôles, catégories, offres) chargées une fois. */
    protected bool $seed = true;

    /** Utilisateur vérifié avec son espace personnel. */
    protected function user(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(OrganizationService::class)->createPersonal($user);

        return $user->fresh();
    }

    protected function stable(User $owner, string $name = 'Écurie du Test'): Organization
    {
        $org = app(OrganizationService::class)->createStable($owner, ['name' => $name]);
        $this->grantLicense($org, 'ecurie');

        return $org;
    }

    protected function grantLicense(Organization $org, string $planSlug = 'multi-chevaux', string $status = 'active'): License
    {
        return License::create([
            'organization_id' => $org->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', $planSlug)->value('id'),
            'source' => 'manual', 'status' => $status, 'starts_at' => now()->subDay(), 'notes' => 'test',
        ]);
    }

    protected function horseFor(User $owner, ?Organization $org = null, array $attributes = []): Horse
    {
        $org ??= $owner->personalOrganization();

        return app(HorseService::class)->create($org, $owner, array_merge(['official_name' => 'TORNADE '.uniqid(), 'sex' => 'female'], $attributes), $org->isPersonal());
    }

    /**
     * Membre de l'espace du cheval avec un rôle sur mesure : hors propriétaire, la seule façon d'avoir des droits
     * sur le budget du cheval (un partage ne les donne jamais, 2.42.1).
     */
    protected function memberWith(Horse $horse, array $permissions, string $name = 'Comptable'): User
    {
        $user = $this->user();
        $org = Organization::findOrFail($horse->organization_id);
        $role = Role::create(['organization_id' => $org->id, 'key' => 'custom-'.Str::lower(Str::random(8)), 'name' => $name, 'is_system' => false]);
        $role->syncPermissionKeys($permissions);
        app(OrganizationService::class)->addMember($org, $user, $role);

        return $user->fresh();
    }

    /** Plusieurs chevaux : affiche « Tous les chevaux » plutôt que le cheval actif. */
    protected function allHorses(User $user): void
    {
        $user->profile()->updateOrCreate([], ['all_horses' => true, 'active_horse_id' => null]);
        $user->unsetRelation('profile');
        app()->forgetInstance(HorseAccess::class);
    }
}
