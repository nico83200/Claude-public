<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Services\Entitlements;
use App\Services\Upsell;
use Tests\TestCase;

/** Offre « Propriétaire » : un cheval ; plusieurs chevaux → « Propriétaire multi-chevaux ». */
class OwnerPlanMigrationTest extends TestCase
{
    public function test_existing_installations_switch_plan_and_current_subscribers_keep_three_horses(): void
    {
        $plan = SubscriptionPlan::where('slug', 'particulier')->firstOrFail();
        $plan->update(['name' => 'Particulier', 'max_horses' => 3, 'description' => 'Jusqu\'à 3 chevaux, partage et exports.']);
        $subscriber = $this->user();
        $org = $subscriber->personalOrganization();
        $license = License::create(['organization_id' => $org->id, 'subscription_plan_id' => $plan->id, 'source' => 'stripe', 'status' => 'active', 'starts_at' => now()->subMonth()]);
        $ended = License::create(['organization_id' => $this->user()->personalOrganization()->id, 'subscription_plan_id' => $plan->id, 'source' => 'stripe', 'status' => 'expired', 'starts_at' => now()->subYear(), 'ends_at' => now()->subMonth()]);

        (require database_path('migrations/2026_01_01_002400_owner_plan_single_horse.php'))->up();

        $plan->refresh();
        $this->assertSame('Propriétaire', $plan->name);
        $this->assertSame(1, $plan->max_horses);
        $this->assertSame(['max_horses' => 3], $license->fresh()->limit_overrides);
        $this->assertNull($ended->fresh()->limit_overrides);
        app(Entitlements::class)->flush();
        $this->assertSame(3, app(Entitlements::class)->limit($org, 'max_horses'));

        // Une offre déjà retouchée dans l'administration n'est pas modifiée.
        $plan->update(['name' => 'Ma formule', 'max_horses' => 2]);
        (require database_path('migrations/2026_01_01_002400_owner_plan_single_horse.php'))->up();
        $this->assertSame('Ma formule', $plan->fresh()->name);
    }

    public function test_one_horse_limit_points_to_multi_horse_plan(): void
    {
        $user = $this->user();
        $org = $user->personalOrganization();
        License::create(['organization_id' => $org->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'particulier')->value('id'), 'source' => 'manual', 'status' => 'active', 'starts_at' => now()]);
        app(Entitlements::class)->flush();
        $this->horseFor($user);
        $this->actingAs($user)->get('/chevaux/nouveau')->assertSee('Propriétaire multi-chevaux');
    }
}
