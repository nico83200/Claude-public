<?php

namespace Tests\Feature;

use App\Models\HorseOrganizationAssignment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\OrganizationService;
use Tests\TestCase;

/** Suppression d'un espace par le super-administrateur. */
class AdminDeleteOrganizationTest extends TestCase
{
    public function test_super_admin_deletes_a_stable_with_confirmation(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $as = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $owner = $this->user();
        $stable = $this->stable($owner, 'Écurie à fermer');
        $own = $this->horseFor($owner, $stable);
        $member = $this->user();
        app(OrganizationService::class)->addMember($stable, $member, 'rider');
        $member->forceFill(['current_organization_id' => $stable->id])->save();
        // Cheval d'un particulier confié à l'écurie : il doit lui être rendu.
        $client = $this->user();
        $boarder = $this->horseFor($client);
        HorseOrganizationAssignment::forceCreate(['horse_id' => $boarder->id, 'organization_id' => $stable->id, 'kind' => 'boarding', 'status' => 'active', 'requested_by' => $owner->id, 'permissions' => '[]']);

        $as()->get('/admin/organisations/'.$stable->id)->assertOk()->assertSee('data-delete-org', false)->assertSee('Supprimer définitivement');
        $as()->delete('/admin/organisations/'.$stable->id, ['confirm_name' => 'mauvais nom'])->assertSessionHasErrors('confirm_name');
        $this->assertNotSoftDeleted($stable);

        $as()->delete('/admin/organisations/'.$stable->id, ['confirm_name' => 'Écurie à fermer'])->assertRedirect('/admin/organisations')->assertSessionHas('success');
        $this->assertSoftDeleted($stable);
        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($boarder);
        $this->assertSame('ended', HorseOrganizationAssignment::where('horse_id', $boarder->id)->value('status'));
        $this->assertDatabaseMissing('organization_members', ['organization_id' => $stable->id]);
        $this->assertSame($member->personalOrganization()->id, $member->fresh()->current_organization_id);
        $this->actingAs($member->fresh())->get('/dashboard')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.organization_deleted']);
    }

    public function test_personal_space_and_paid_space_are_protected(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $as = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $user = $this->user();
        $personal = $user->personalOrganization();
        $as()->get('/admin/organisations/'.$personal->id)->assertSee('espace personnel d&#039;un compte', false)->assertDontSee('Supprimer définitivement');
        $as()->delete('/admin/organisations/'.$personal->id, ['confirm_name' => $personal->name])->assertSessionHas('error');
        $this->assertNotSoftDeleted($personal);

        $stable = $this->stable($this->user(), 'Écurie payante');
        Subscription::forceCreate(['organization_id' => $stable->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'ecurie')->value('id'), 'stripe_subscription_id' => 'sub_1', 'stripe_status' => 'active', 'interval' => 'month']);
        $as()->delete('/admin/organisations/'.$stable->id, ['confirm_name' => 'Écurie payante'])->assertSessionHas('error');
        $this->assertNotSoftDeleted($stable);

        $this->actingAs($user)->delete('/admin/organisations/'.$stable->id, ['confirm_name' => 'Écurie payante'])->assertNotFound();
    }
}
