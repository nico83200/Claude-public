<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\OrganizationService;
use Tests\TestCase;

/** Comptes créés avant la règle « une écurie par compte » : le super-administrateur choisit l'écurie conservée. */
class DuplicateStablesAdminTest extends TestCase
{
    private function legacyStable($owner, string $name): Organization
    {
        // Écurie créée avant la règle (le service la refuserait désormais).
        $org = Organization::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name).'-'.\Illuminate\Support\Str::random(4), 'type' => 'stable', 'owner_id' => $owner->id]);
        app(OrganizationService::class)->addMember($org, $owner, 'owner');

        return $org;
    }

    public function test_super_admin_keeps_one_stable_and_others_are_suspended(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $owner = $this->user(['name' => 'Paul Bernard']);
        $a = $this->legacyStable($owner, 'Écurie des Tilleuls');
        $b = $this->legacyStable($owner, 'Écurie du Lac');
        $c = $this->legacyStable($owner, 'Écurie test');
        $owner->forceFill(['current_organization_id' => $c->id])->save();
        $single = $this->user();
        $this->legacyStable($single, 'Écurie unique');

        $as = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $as()->get('/admin/organisations')->assertOk()->assertSee('data-duplicate-stables', false)->assertSee('Comptes avec plusieurs écuries (1)')
            ->assertSee('Écurie du Lac')->assertDontSee('Écurie unique · ', false);

        $as()->post('/admin/organisations/ecuries-en-double/'.$owner->id, ['keep_id' => $b->id])->assertSessionHas('success');
        $this->assertNull($b->fresh()->suspended_at);
        $this->assertNotNull($a->fresh()->suspended_at);
        $this->assertNotNull($c->fresh()->suspended_at);
        $this->assertStringContainsString('Écurie du Lac', $a->fresh()->suspension_reason);
        $this->assertSame($b->id, $owner->fresh()->current_organization_id);
        $this->assertSame($b->id, $owner->fresh()->ownedStable()->id);
        $this->assertDatabaseHas('organizations', ['id' => $a->id]); // rien n'est supprimé
        $as()->get('/admin/organisations')->assertDontSee('data-duplicate-stables', false);

        // Une écurie d'un autre compte ne peut pas être choisie ; réservé au super-administrateur.
        $as()->post('/admin/organisations/ecuries-en-double/'.$owner->id, ['keep_id' => Organization::where('name', 'Écurie unique')->value('id')])->assertNotFound();
        $this->actingAs($owner)->post('/admin/organisations/ecuries-en-double/'.$owner->id, ['keep_id' => $a->id])->assertNotFound();
    }
}
