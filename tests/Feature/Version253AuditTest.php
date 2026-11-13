<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RidingSession;
use App\Models\User;
use App\Support\AuditLabels;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Historique des actions de tous les utilisateurs (super-admin) : notées d'office, recherche, export. */
class Version253AuditTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = $this->user(['name' => 'Super ADMIN']);
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    public function test_user_actions_are_recorded_without_form_content(): void
    {
        $owner = $this->user(['name' => 'Paul MARTIN', 'email' => 'paul@exemple.fr']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        AuditLog::query()->delete();

        // Ajout d'une séance : une ligne, sans le texte saisi.
        $this->actingAs($owner)->post(route('sessions.store'), ['horse_id' => $horse->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'session_type' => 'ride', 'planned_minutes' => 45, 'objective' => 'Secret du cavalier']);
        $log = AuditLog::where('action', 'sessions.store')->sole();
        $this->assertSame([$owner->id, 'Séance · ajout'], [$log->user_id, AuditLabels::label($log->action)]);
        $this->assertStringNotContainsString('Secret', json_encode($log->properties));

        // Modification d'un élément du cheval : l'élément et le cheval sont repérés.
        $session = RidingSession::sole();
        $this->actingAs($owner)->delete(route('sessions.destroy', $session));
        $this->assertSame(RidingSession::class, AuditLog::where('action', 'sessions.destroy')->sole()->subject_type);

        // Une saisie refusée (erreurs) ou une simple lecture ne sont pas notées ; une action déjà détaillée ne l'est qu'une fois.
        $this->actingAs($owner)->post(route('sessions.store'), []);
        $this->actingAs($owner)->get(route('dashboard'));
        $this->assertSame(1, AuditLog::where('action', 'sessions.store')->count());
        $this->actingAs($owner)->post(route('horses.archive', $horse));
        $this->assertSame(0, AuditLog::where('action', 'horses.archive')->count(), 'Déjà détaillée par horse.archived.');
        $this->assertSame(1, AuditLog::where('action', 'horse.archived')->count());
    }

    public function test_logins_and_logouts_are_recorded(): void
    {
        $user = $this->user(['email' => 'lea@exemple.fr', 'password' => bcrypt('Mot2Passe!long')]);
        $this->post(route('login.store'), ['email' => 'lea@exemple.fr', 'password' => 'Mot2Passe!long']);
        $this->assertSame(1, AuditLog::where('action', 'auth.login')->where('user_id', $user->id)->count());
        $this->post(route('login.store'), ['email' => 'lea@exemple.fr', 'password' => 'mauvais']);
        $this->assertSame(1, AuditLog::where('action', 'auth.login')->count());
        $this->actingAs($user)->post(route('logout'));
        $this->assertSame(1, AuditLog::where('action', 'auth.logout')->where('user_id', $user->id)->count());
    }

    public function test_super_admin_searches_by_action_user_date_and_exports(): void
    {
        $admin = $this->admin();
        $paul = $this->user(['name' => 'Paul MARTIN', 'email' => 'paul@exemple.fr']);
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        AuditLog::query()->delete();
        AuditLog::create(['user_id' => $paul->id, 'action' => 'sessions.store', 'ip_address' => '92.184.1.1', 'properties' => ['auto' => true], 'created_at' => '2026-11-01 10:00']);
        AuditLog::create(['user_id' => $lea->id, 'action' => 'horses.care.destroy', 'ip_address' => '81.2.3.4', 'properties' => ['auto' => true], 'created_at' => '2026-11-10 18:00']);
        AuditLog::create(['user_id' => $lea->id, 'action' => 'auth.login', 'ip_address' => '81.2.3.4', 'created_at' => '2026-11-12 08:00']);

        $this->actingAs($this->user())->get(route('admin.audit.index'))->assertNotFound(); // l'administration reste invisible
        $page = fn (array $q) => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.audit.index', $q))->assertOk();
        $page([])->assertSee('Historique des actions')->assertSee('Séance · ajout')->assertSee('Soin · suppression')->assertSee('3 actions');
        // Par libellé en clair (sans accents), par code, par personne (nom ou e-mail), par période, catégorie et IP.
        $page(['q' => 'seance'])->assertSee('1 action')->assertSee('Paul MARTIN');
        $page(['q' => 'suppression'])->assertSee('Soin · suppression')->assertDontSee('Séance · ajout');
        $page(['q' => 'horses.care'])->assertSee('1 action');
        $page(['user' => 'durand'])->assertSee('2 actions');
        $page(['user' => 'paul@'])->assertSee('1 action');
        $page(['from' => '2026-11-05', 'to' => '2026-11-11'])->assertSee('1 action')->assertSee('Soin · suppression');
        $page(['category' => 'auth'])->assertSee('1 action')->assertSee('Connexion');
        $page(['ip' => '92.184'])->assertSee('1 action');
        $page(['q' => 'introuvable'])->assertSee('Aucune action ne correspond');

        $csv = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.audit.export', ['user' => 'durand']))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Soin · suppression";horses.care.destroy;"Léa DURAND";lea@exemple.fr', $csv);
        $this->assertStringNotContainsString('Paul MARTIN', $csv);
        $this->assertSame(1, AuditLog::where('action', 'admin.audit_exported')->count());
    }

    public function test_routine_entries_are_purged_after_one_year_sensitive_ones_kept(): void
    {
        AuditLog::query()->delete();
        AuditLog::create(['action' => 'sessions.store', 'properties' => ['auto' => true], 'created_at' => now()->subMonths(13)]);
        AuditLog::create(['action' => 'auth.login', 'created_at' => now()->subMonths(13)]);
        AuditLog::create(['action' => 'grant.revoked', 'created_at' => now()->subMonths(13)]);
        AuditLog::create(['action' => 'sessions.update', 'properties' => ['auto' => true], 'created_at' => now()->subMonths(2)]);
        $this->artisan('data:purge')->assertSuccessful();
        $this->assertEqualsCanonicalizing(['grant.revoked', 'sessions.update'], AuditLog::pluck('action')->all());
    }
}
