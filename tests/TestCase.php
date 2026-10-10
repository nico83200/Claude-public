<?php

namespace Tests;

use App\Models\Horse;
use App\Models\License;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\HorseService;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
}
