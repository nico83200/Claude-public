<?php

namespace Tests\Feature;

use App\Models\DataRequest;
use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Entitlements;
use Tests\TestCase;

class AdminTest extends TestCase
{
    private function admin(): User
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function asAdmin(User $admin)
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_admin_area_is_hidden_from_regular_users(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/admin')->assertNotFound();
        $this->actingAs($user)->post('/admin/offres', ['name' => 'Hack'])->assertNotFound();
    }

    public function test_admin_requires_password_confirmation(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertRedirect('/user/confirm-password');
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->admin();
        $org = $this->user()->personalOrganization();
        foreach (['/admin', '/admin/utilisateurs', '/admin/organisations', "/admin/organisations/{$org->id}", '/admin/offres', '/admin/offres/nouveau', '/admin/facturation', '/admin/parametres', '/admin/journal', '/admin/erreurs', '/admin/demandes-rgpd', "/admin/utilisateurs/{$admin->id}"] as $url) {
            $this->asAdmin($admin)->get($url)->assertOk();
        }
    }

    public function test_plan_creation_update_and_archive(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin)->post('/admin/offres', ['name' => 'Club', 'currency' => 'EUR', 'audience' => 'stable', 'price_monthly' => '99.00', 'max_horses' => 200, 'features' => ['stable', 'sharing'], 'is_active' => '1', 'is_public' => '1'])->assertRedirect('/admin/offres');
        $plan = SubscriptionPlan::where('name', 'Club')->firstOrFail();
        $this->assertSame(200, $plan->max_horses);

        $this->asAdmin($admin)->put("/admin/offres/{$plan->id}", ['name' => 'Club', 'currency' => 'EUR', 'audience' => 'stable', 'price_monthly' => '99.00', 'max_horses' => 300, 'features' => ['stable'], 'is_active' => '1'])->assertRedirect();
        $this->assertSame(300, $plan->fresh()->max_horses);
        $this->assertSame(['stable'], $plan->fresh()->features);

        // Migration tarifaire sans préavis suffisant refusée
        $this->asAdmin($admin)->put("/admin/offres/{$plan->id}", ['name' => 'Club', 'currency' => 'EUR', 'audience' => 'stable', 'price_monthly' => '109.00', 'existing_subscribers' => 'migrate_after_notice', 'migration_effective_on' => now()->addDays(5)->toDateString(), 'is_active' => '1'])
            ->assertSessionHasErrors('migration_effective_on');
        $this->assertSame('99.00', $plan->fresh()->price_monthly);

        $this->asAdmin($admin)->post("/admin/offres/{$plan->id}/archiver")->assertRedirect();
        $this->assertNotNull($plan->fresh()->archived_at);
        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id]); // jamais supprimée physiquement
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.plan_archived']);
    }

    public function test_limit_changes_apply_to_existing_licenses(): void
    {
        $admin = $this->admin();
        $user = $this->user();
        $plan = SubscriptionPlan::where('slug', 'decouverte')->first();
        $this->asAdmin($admin)->put("/admin/offres/{$plan->id}", ['name' => $plan->name, 'currency' => 'EUR', 'audience' => 'individual', 'max_horses' => 2, 'features' => ['offline'], 'is_active' => '1', 'is_default_free' => '1'])->assertRedirect();
        $this->horseFor($user);
        $this->horseFor($user); // désormais autorisé : limite à 2
        $this->assertSame(2, $user->personalOrganization()->horses()->count());
    }

    public function test_suspend_and_reactivate_user(): void
    {
        $admin = $this->admin();
        $user = $this->user(['password' => 'Cheval2026x']);
        $this->asAdmin($admin)->post("/admin/utilisateurs/{$user->id}/suspendre", ['reason' => 'Fraude'])->assertRedirect();
        $this->assertNotNull($user->fresh()->suspended_at);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'Cheval2026x'])->assertSessionHasErrors('email');

        $this->asAdmin($admin)->post("/admin/utilisateurs/{$user->id}/reactiver")->assertRedirect();
        $this->assertNull($user->fresh()->suspended_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user_suspended', 'subject_id' => $user->id]);
    }

    public function test_super_admin_cannot_be_suspended_from_ui(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $this->asAdmin($admin)->post("/admin/utilisateurs/{$other->id}/suspendre", ['reason' => 'x'])->assertStatus(422);
        $this->assertNull($other->fresh()->suspended_at);
    }

    public function test_manual_license_grants_rights_without_payment(): void
    {
        $admin = $this->admin();
        $user = $this->user();
        $org = $user->personalOrganization();
        $plan = SubscriptionPlan::where('slug', 'multi-chevaux')->first();
        $this->asAdmin($admin)->post("/admin/organisations/{$org->id}/licences", ['subscription_plan_id' => $plan->id, 'status' => 'active', 'notes' => 'Partenariat club', 'limit_overrides' => ['max_horses' => 25]])->assertRedirect();

        $license = License::firstOrFail();
        $this->assertSame('manual', $license->source);
        $this->assertNull($license->subscription_id);
        $this->assertDatabaseCount('payment_records', 0);
        $ent = app(Entitlements::class)->for($org);
        $this->assertSame(25, $ent['limits']['max_horses']);
        $this->assertSame('multi-chevaux', $ent['plan']->slug);

        $this->asAdmin($admin)->post("/admin/organisations/{$org->id}/licences", ['subscription_plan_id' => $plan->id, 'status' => 'active'])->assertSessionHasErrors('notes');
    }

    public function test_suspended_organization_becomes_read_only(): void
    {
        $admin = $this->admin();
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->asAdmin($admin)->post("/admin/organisations/{$horse->organization_id}/suspendre", ['reason' => 'Impayés'])->assertRedirect();
        $this->actingAs($user)->get("/chevaux/{$horse->id}")->assertOk();
        $this->actingAs($user)->put("/chevaux/{$horse->id}", ['official_name' => 'X', 'sex' => 'male'])->assertForbidden();
        $this->asAdmin($admin)->post("/admin/organisations/{$horse->organization_id}/reactiver");
        $this->actingAs($user)->put("/chevaux/{$horse->id}", ['official_name' => 'X', 'sex' => 'male'])->assertRedirect();
    }

    public function test_create_super_admin_command(): void
    {
        $this->artisan('app:create-super-admin', ['email' => 'boss@example.com', '--name' => 'Boss'])
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'Tr3s-Solide!Mdp')
            ->expectsQuestion('Confirmez le mot de passe', 'Tr3s-Solide!Mdp')
            ->assertSuccessful();
        $user = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertNotNull($user->personalOrganization());

        $this->artisan('app:create-super-admin', ['email' => 'boss@example.com'])->assertFailed();
        $regular = $this->user();
        $this->artisan('app:create-super-admin', ['email' => $regular->email, '--promote' => true])->assertSuccessful();
        $this->assertTrue($regular->fresh()->is_super_admin);
    }

    public function test_is_super_admin_cannot_be_mass_assigned(): void
    {
        $this->post('/register', ['name' => 'Malin', 'email' => 'malin@example.com', 'password' => 'Cheval2026x', 'password_confirmation' => 'Cheval2026x', 'terms' => '1', 'is_super_admin' => '1']);
        $this->assertFalse((bool) User::where('email', 'malin@example.com')->value('is_super_admin'));
    }

    public function test_gdpr_deletion_request_anonymizes_account(): void
    {
        $admin = $this->admin();
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/parametres/donnees/demande', ['type' => 'deletion'])->assertRedirect();
        $request = DataRequest::firstOrFail();
        $this->asAdmin($admin)->put("/admin/demandes-rgpd/{$request->id}", ['status' => 'in_progress', 'execute_deletion' => '1'])->assertRedirect();

        $trashed = User::withTrashed()->find($user->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertStringContainsString('@invalid.local', $trashed->email);
        $this->assertSoftDeleted('horses', ['id' => $horse->id]);
        $this->assertSame('done', $request->fresh()->status);
    }
}
