<?php

namespace Tests\Feature;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HorseAccessGrant;
use App\Models\HorseOrganizationAssignment;
use App\Models\Role;
use App\Services\OrganizationService;
use App\Support\Perm;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cloisonnement des données entre comptes et organisations, et partages.
 */
class AccessControlTest extends TestCase
{
    public function test_user_cannot_access_another_users_horse_by_changing_ids(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $horse = $this->horseFor($alice);
        $care = $this->care($horse, $alice);

        $this->actingAs($bob)->get("/chevaux/{$horse->id}")->assertForbidden();
        $this->actingAs($bob)->get("/chevaux/{$horse->id}/sante")->assertForbidden();
        $this->actingAs($bob)->get("/chevaux/{$horse->id}/soins/{$care->id}")->assertForbidden();
        $this->actingAs($bob)->put("/chevaux/{$horse->id}", ['official_name' => 'Piraté', 'sex' => 'male'])->assertForbidden();
        $this->assertNotSame('Piraté', $horse->fresh()->official_name);
        $this->actingAs($bob)->get('/chevaux')->assertOk()->assertDontSee($horse->official_name);
        $this->actingAs($bob)->get("/chevaux/{$horse->id}/export/fiche.pdf")->assertForbidden();
    }

    public function test_nested_resources_are_scoped_to_their_horse(): void
    {
        $alice = $this->user();
        $this->grantLicense($alice->personalOrganization());
        $h1 = $this->horseFor($alice);
        $h2 = $this->horseFor($alice);
        $care = $this->care($h2, $alice);

        $this->actingAs($alice)->get("/chevaux/{$h1->id}/soins/{$care->id}")->assertNotFound();
    }

    public function test_half_lease_grant_gives_only_selected_rights(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization());
        $rider = $this->user();
        $horse = $this->horseFor($owner);
        $other = $this->horseFor($owner, null, ['official_name' => 'AUTRE CHEVAL']);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => Perm::halfLeasePreset(), 'granted_by' => $owner->id]);

        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertOk();
        $this->actingAs($rider)->get("/chevaux/{$other->id}")->assertForbidden();
        $this->actingAs($rider)->get("/chevaux/{$horse->id}/modifier")->assertForbidden();
        $this->actingAs($rider)->post('/espaces/changer', ['organization_id' => $horse->organization_id])->assertForbidden();
        $this->actingAs($rider)->get("/chevaux/{$horse->id}/partage")->assertForbidden();
        $this->actingAs($rider)->get("/chevaux/{$horse->id}/soins/nouveau")->assertForbidden();
        // Les dépenses ne sont pas incluses dans le préréglage demi-pension.
        Expense::forceCreate(['uuid' => (string) Str::uuid(), 'organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::first()->id, 'spent_on' => now(), 'amount' => 123.45, 'supplier' => 'SECRET FOURNISSEUR']);
        $this->actingAs($rider)->get('/budget')->assertOk()->assertDontSee('SECRET FOURNISSEUR');
        // Elle peut créer une séance.
        $this->actingAs($rider)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->format('Y-m-d H:i'), 'session_type' => 'free'])->assertRedirect();
        $this->assertDatabaseHas('riding_sessions', ['horse_id' => $horse->id, 'created_by' => $rider->id]);
    }

    public function test_revocation_is_immediate(): void
    {
        $owner = $this->user();
        $rider = $this->user();
        $horse = $this->horseFor($owner);
        $grant = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => Perm::halfLeasePreset(), 'granted_by' => $owner->id]);
        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertOk();

        $this->actingAs($owner)->post("/chevaux/{$horse->id}/partage/acces/{$grant->id}/revoquer")->assertRedirect();
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'grant.revoked']);
    }

    public function test_expired_and_future_grants_give_no_access(): void
    {
        $owner = $this->user();
        $rider = $this->user();
        $horse = $this->horseFor($owner);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW], 'expires_at' => now()->subMinute()]);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW], 'starts_at' => now()->addDay()]);
        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertForbidden();
    }

    public function test_stable_manager_has_no_implicit_access_to_boarded_horse_health(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $stable = $this->stable($manager);
        $horse = $this->horseFor($owner);
        $this->care($horse, $owner, 'Diagnostic confidentiel');
        HorseOrganizationAssignment::create(['horse_id' => $horse->id, 'organization_id' => $stable->id, 'permissions' => Perm::boardingPreset(), 'status' => 'active', 'starts_at' => now()]);

        $this->actingAs($manager)->get("/chevaux/{$horse->id}")->assertOk();
        $this->actingAs($manager)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertDontSee('Diagnostic confidentiel');
        $this->actingAs($manager)->get("/chevaux/{$horse->id}/documents")->assertForbidden();

        // Fin de prise en charge : plus d'accès, historique conservé.
        HorseOrganizationAssignment::where('horse_id', $horse->id)->update(['status' => 'ended', 'ends_at' => now()]);
        $this->actingAs($manager)->get("/chevaux/{$horse->id}")->assertForbidden();
        $this->assertDatabaseHas('horses', ['id' => $horse->id, 'deleted_at' => null]);
    }

    public function test_organizations_are_isolated(): void
    {
        $m1 = $this->user();
        $m2 = $this->user();
        $s1 = $this->stable($m1, 'Écurie A');
        $s2 = $this->stable($m2, 'Écurie B');
        $horseA = $this->horseFor($m1, $s1);
        $this->actingAs($m2)->get("/chevaux/{$horseA->id}")->assertForbidden();
        $this->actingAs($m2)->post('/espaces/changer', ['organization_id' => $s1->id])->assertForbidden();
    }

    public function test_stable_roles_permissions(): void
    {
        $manager = $this->user();
        $stable = $this->stable($manager);
        $rider = $this->user();
        $care = $this->user();
        $orgs = app(OrganizationService::class);
        $orgs->addMember($stable, $rider, 'rider');
        $orgs->addMember($stable, $care, 'care_manager');
        $horse = $this->horseFor($manager, $stable);

        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertOk();
        $this->actingAs($rider)->get("/chevaux/{$horse->id}/soins/nouveau")->assertForbidden();
        $this->actingAs($rider)->get("/chevaux/{$horse->id}/modifier")->assertForbidden();
        $this->actingAs($care)->get("/chevaux/{$horse->id}/soins/nouveau")->assertOk();
        $rider->forceFill(['current_organization_id' => $stable->id])->save();
        $this->actingAs($rider)->get('/abonnement')->assertForbidden();
        $this->actingAs($rider)->post('/organisation/membres', ['email' => 'x@example.com', 'role_id' => Role::system('staff')->id])->assertForbidden();
        $this->assertDatabaseMissing('invitations', ['email' => 'x@example.com']);
    }

    public function test_removed_member_loses_access_immediately(): void
    {
        $manager = $this->user();
        $stable = $this->stable($manager);
        $staff = $this->user();
        $member = app(OrganizationService::class)->addMember($stable, $staff, 'staff');
        $horse = $this->horseFor($manager, $stable);
        $this->actingAs($staff)->get("/chevaux/{$horse->id}")->assertOk();

        $this->actingAs($manager)->delete("/organisation/membres/{$member->id}")->assertRedirect();
        $this->actingAs($staff)->get("/chevaux/{$horse->id}")->assertForbidden();
    }

    public function test_super_admin_has_no_implicit_business_access(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->get("/chevaux/{$horse->id}")->assertForbidden();
    }

    public function test_search_only_returns_authorized_data(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $this->horseFor($alice, null, ['official_name' => 'ZORRO SECRET']);
        $this->actingAs($bob)->get('/recherche?q=ZORRO')->assertOk()->assertDontSee('ZORRO SECRET');
        $this->actingAs($alice)->get('/recherche?q=ZORRO')->assertOk()->assertSee('ZORRO SECRET');
    }

    private function care($horse, $author, string $diagnosis = 'RAS'): CareRecord
    {
        $c = new CareRecord(['care_category_id' => CareCategory::first()->id, 'performed_at' => now(), 'reason' => 'Contrôle', 'diagnosis' => $diagnosis]);
        $c->horse_id = $horse->id;
        $c->author_id = $author->id;
        $c->save();

        return $c;
    }
}
