<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\DailyLog;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\Invitation;
use App\Models\License;
use App\Models\Organization;
use App\Models\RidingSession;
use App\Models\SimulationBatch;
use App\Models\User;
use App\Services\HorseAccess;
use App\Services\LoadSimulation;
use App\Services\NavigationSimulation;
use App\Services\WeeklyDigest;
use App\Support\Perm;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Simulation de charge (super-admin) : comptes de test créés par étapes, état du serveur, suppression complète. */
class LoadSimulationTest extends TestCase
{
    private function admin(): User
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function as(User $admin)
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    private const PARAMS = ['accounts' => 3, 'horses' => 1, 'events' => 2, 'sessions' => 2, 'cares' => 1, 'expenses' => 1, 'logs' => 2, 'past_days' => 10, 'future_days' => 5, 'chunk' => 2];

    public function test_only_super_admins_reach_the_simulation(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.simulation.index'))->assertNotFound(); // l'administration reste invisible
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->postJson(route('admin.simulation.store'), self::PARAMS)->assertNotFound();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->getJson(route('admin.simulation.status'))->assertNotFound();
        $this->assertSame(0, SimulationBatch::count());
    }

    public function test_simulation_creates_accounts_step_by_step_then_purges_everything(): void
    {
        $admin = $this->admin();
        $real = $this->user();
        $realHorse = $this->horseFor($real);
        $this->as($admin)->get(route('admin.simulation.index'))->assertOk()->assertSee('data-server-monitor', false)->assertSee('Lancer la simulation');

        $run = $this->as($admin)->postJson(route('admin.simulation.store'), self::PARAMS)->assertOk()->assertJson(['status' => 'running', 'total' => 3])->json();
        // Une seule simulation à la fois.
        $this->as($admin)->postJson(route('admin.simulation.store'), self::PARAMS)->assertStatus(422);

        $step = $this->as($admin)->postJson(route('admin.simulation.step', $run['id']))->assertOk()->json();
        $this->assertSame(2, $step['done']);
        $this->assertSame('running', $step['status']);
        $step = $this->as($admin)->postJson(route('admin.simulation.step', $run['id']))->assertOk()->json();
        $this->assertSame(['done', 3], [$step['status'], $step['done']]);
        $this->assertSame(3, $step['counts']['accounts']);
        $this->assertSame(3, $step['counts']['horses']);
        $this->assertSame(6, $step['counts']['events']);

        $simulated = User::whereNotNull('simulation_batch_id')->get();
        $this->assertCount(3, $simulated);
        $this->assertTrue($simulated->every(fn ($u) => str_ends_with($u->email, '@'.LoadSimulation::DOMAIN) && $u->email_verified_at));
        $horses = Horse::whereIn('organization_id', Organization::whereIn('owner_id', $simulated->pluck('id'))->pluck('id'))->get();
        $this->assertCount(3, $horses);
        $this->assertTrue($horses->every->is_demo);
        $this->assertSame(6, RidingSession::whereIn('horse_id', $horses->pluck('id'))->count());
        $this->assertSame(6, DailyLog::whereIn('horse_id', $horses->pluck('id'))->count());
        $this->assertGreaterThanOrEqual(6, CalendarEvent::whereIn('horse_id', $horses->pluck('id'))->count());
        // Aucune licence ni abonnement pour les comptes de test.
        $this->assertSame(0, License::whereIn('organization_id', $horses->pluck('organization_id'))->count());
        // Indicateurs : simulations comptées par défaut (bandeau), retirées avec « ?simulations=0 ».
        $with = $this->as($admin)->get(route('admin.dashboard'))->assertSee('data-simulation-scope="with"', false)->assertSee('3 comptes de test')->viewData('counts');
        $without = $this->as($admin)->get(route('admin.dashboard', ['simulations' => 0]))->assertSee('data-simulation-scope="without"', false)->viewData('counts');
        $this->assertSame(User::count(), $with['users']);
        $this->assertSame(User::whereNull('simulation_batch_id')->count(), $without['users']);
        $this->assertSame(3, $with['horses'] - $without['horses']);
        $this->assertSame(3, $with['organizations'] - $without['organizations']);
        $statsWith = $this->as($admin)->get(route('admin.stats.index'))->assertOk();
        $statsWithout = $this->as($admin)->get(route('admin.stats.index', ['simulations' => 0]))->assertOk()
            ->assertSee(e(route('admin.stats.index', ['jours' => 90, 'simulations' => 0])), false);
        $this->assertSame(3, $statsWith->viewData('kpis')['users'] - $statsWithout->viewData('kpis')['users']);
        $this->assertSame(6, $statsWith->viewData('usage')['Séances et cours'] - $statsWithout->viewData('usage')['Séances et cours']);
        $this->assertSame(3, $statsWith->viewData('usage')['Soins'] - $statsWithout->viewData('usage')['Soins']);
        $this->assertSame(3, $statsWith->viewData('usage')['Dépenses'] - $statsWithout->viewData('usage')['Dépenses']);

        // État du serveur.
        $this->as($admin)->getJson(route('admin.simulation.status'))->assertOk()
            ->assertJsonStructure(['at', 'php' => ['version', 'memory_limit'], 'system' => ['load'], 'database' => ['ok', 'ping_ms'], 'app' => ['queue_pending', 'simulated_users']])
            ->assertJsonPath('app.simulated_users', 3);

        // Suppression par étapes : tout disparaît, le reste est intact.
        $res = $this->as($admin)->postJson(route('admin.simulation.purge', $run['id']))->assertOk()->json();
        $this->assertSame(0, $res['left']);
        $this->assertSame('purged', SimulationBatch::find($run['id'])->status);
        $this->assertSame(0, User::withTrashed()->whereNotNull('simulation_batch_id')->count());
        $this->assertSame(0, Horse::withTrashed()->whereIn('id', $horses->pluck('id'))->count());
        $this->assertSame(0, RidingSession::withTrashed()->whereIn('horse_id', $horses->pluck('id'))->count());
        $this->assertSame(0, Organization::whereIn('id', $horses->pluck('organization_id'))->count());
        $this->assertNotNull($realHorse->fresh());
        $this->assertNotNull($real->fresh());
    }

    public function test_stop_and_resume(): void
    {
        $admin = $this->admin();
        $run = $this->as($admin)->postJson(route('admin.simulation.store'), self::PARAMS)->json();
        $this->as($admin)->postJson(route('admin.simulation.stop', $run['id']))->assertJson(['status' => 'stopped']);
        $this->assertSame(0, $this->as($admin)->postJson(route('admin.simulation.step', $run['id']))->json('done'), 'Arrêtée : plus rien n\'est créé.');
        $this->as($admin)->postJson(route('admin.simulation.resume', $run['id']))->assertJson(['status' => 'running']);
        $this->assertSame(2, $this->as($admin)->postJson(route('admin.simulation.step', $run['id']))->json('done'));
    }

    public function test_limits_are_enforced(): void
    {
        $admin = $this->admin();
        $this->as($admin)->postJson(route('admin.simulation.store'), ['accounts' => LoadSimulation::MAX_PER_BATCH + 1] + self::PARAMS)->assertStatus(422)->assertJsonValidationErrors('accounts');
        $this->as($admin)->postJson(route('admin.simulation.store'), ['horses' => 9] + self::PARAMS)->assertStatus(422);
        $this->assertSame(0, SimulationBatch::count());
    }

    public function test_no_email_and_no_ai_for_test_accounts(): void
    {
        $admin = $this->admin();
        $run = $this->as($admin)->postJson(route('admin.simulation.store'), ['accounts' => 1, 'chunk' => 1] + self::PARAMS)->json();
        $this->as($admin)->postJson(route('admin.simulation.step', $run['id']));
        $fake = User::whereNotNull('simulation_batch_id')->sole();

        // Un e-mail réellement envoyé vers @simulation.invalid est bloqué avant l'envoi.
        $sent = 0;
        Event::listen(MessageSent::class, function () use (&$sent) {
            $sent++;
        });
        Mail::raw('Essai', fn ($m) => $m->to($fake->email)->subject('Essai'));
        $this->assertSame(0, $sent);
        Mail::raw('Essai', fn ($m) => $m->to('vrai@example.com')->subject('Essai'));
        $this->assertSame(1, $sent);

        $this->assertTrue(LoadSimulation::isSimulated($fake));
        $this->assertFalse(app(WeeklyDigest::class)->introEligible($fake, ['sessions' => collect([1])] + app(WeeklyDigest::class)->for($fake)));
    }

    public function test_shares_are_proposed_to_my_account_with_different_rights(): void
    {
        $admin = $this->admin();
        $params = ['accounts' => 4, 'chunk' => 4, 'shares' => 3, 'events' => 0, 'sessions' => 0, 'cares' => 0, 'expenses' => 0, 'logs' => 0] + self::PARAMS;
        $run = $this->as($admin)->postJson(route('admin.simulation.store'), $params)->json();
        $this->assertSame(3, $this->as($admin)->postJson(route('admin.simulation.step', $run['id']))->json('counts.shares'));

        // Proposés sur l'accueil (en attente), sans e-mail.
        $pending = Invitation::pending()->where('email', $admin->email)->with('horse')->get();
        $this->assertCount(3, $pending);
        $this->assertSame(['Demi-pension', 'Pension (écurie)', 'Lecture seule'], $pending->map(fn ($i) => Str::after($i->horse->usual_name, ' · '))->all());
        $this->assertNotSame($pending[0]->permissions, $pending[2]->permissions);
        $page = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        foreach ($pending as $inv) {
            $page->assertSee(route('invitations.respond', $inv), false);
        }

        // Accepter : les droits du profil s'appliquent (lecture seule : pas de modification de la fiche).
        foreach ($pending as $inv) {
            $this->actingAs($admin)->post(route('invitations.respond', $inv), ['decision' => 'accept']);
        }
        App::forgetInstance(HorseAccess::class);
        $readOnly = $pending[2]->horse;
        $this->actingAs($admin)->get(route('horses.show', $readOnly))->assertOk();
        $this->actingAs($admin)->get(route('horses.edit', $readOnly))->assertForbidden();

        // Suppression : partages et accès disparaissent avec les chevaux de test.
        $this->as($admin)->postJson(route('admin.simulation.purge', $run['id']))->assertJson(['left' => 0]);
        $this->assertSame(0, HorseAccessGrant::where('user_id', $admin->id)->count());
        $this->assertSame(0, Invitation::where('email', $admin->email)->count());
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_shares_can_be_accepted_automatically(): void
    {
        $admin = $this->admin();
        $params = ['accounts' => 7, 'chunk' => 7, 'shares' => 7, 'shares_accept' => true, 'events' => 0, 'sessions' => 0, 'cares' => 0, 'expenses' => 0, 'logs' => 0] + self::PARAMS;
        $run = $this->as($admin)->postJson(route('admin.simulation.store'), $params)->json();
        $this->as($admin)->postJson(route('admin.simulation.step', $run['id']));
        $grants = HorseAccessGrant::where('user_id', $admin->id)->get();
        $this->assertCount(7, $grants);
        $this->assertSame(1, $grants->whereNotNull('expires_at')->count(), 'Un profil limité à 30 jours.');
        $this->assertSame(0, Invitation::pending()->where('email', $admin->email)->count());
        $this->actingAs($admin)->get(route('horses.index'))->assertOk()->assertSee('Tous les droits');
    }

    public function test_bottom_bar_has_no_duplicate_for_a_view_only_shared_horse(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $guest = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $guest->id, 'permissions' => [Perm::HORSE_VIEW]]);
        $html = $this->actingAs($guest)->get(route('dashboard'))->assertOk()->getContent();
        preg_match_all('/data-bar="([^"]+)"/', $html, $m);
        $this->assertSame($m[1], array_values(array_unique($m[1])), 'Chaque entrée de la barre du bas est unique.');
        $this->assertSame(['dashboard', 'calendar.index', 'horses.show'], $m[1]);
    }

    public function test_navigation_renders_real_pages_as_test_accounts(): void
    {
        $admin = $this->admin();
        $this->as($admin)->postJson(route('admin.simulation.navigation'), ['pages' => 3])->assertStatus(422); // pas encore de comptes de test
        $run = $this->as($admin)->postJson(route('admin.simulation.store'), ['accounts' => 2, 'chunk' => 2] + self::PARAMS)->json();
        $this->as($admin)->postJson(route('admin.simulation.step', $run['id']));
        $driver = config('session.driver');

        $visits = $this->as($admin)->postJson(route('admin.simulation.navigation'), ['pages' => 20])->assertOk()->json('visits');
        $this->assertCount(20, $visits);
        foreach ($visits as $v) {
            $this->assertLessThan(400, $v['status'], $v['label'].' ('.$v['path'].') : '.$v['status']);
            $this->assertGreaterThan(0, $v['queries']);
        }
        $this->assertGreaterThan(0, collect($visits)->firstWhere('status', 200)['kb']);
        // L'administrateur garde son identité et sa session ; pilote de session rétabli.
        $this->assertSame($driver, config('session.driver'));
        $this->assertSame($admin->id, auth()->id());
        $this->as($admin)->get(route('admin.simulation.index'))->assertOk()->assertSee('data-navigation-simulation', false);
        // Les comptes de test sont notés vus.
        $this->assertGreaterThan(0, User::whereNotNull('simulation_batch_id')->whereNotNull('last_seen_at')->count());

        // Réservé au super-admin, et jamais au nom d'un vrai compte.
        $this->actingAs($this->user())->postJson(route('admin.simulation.navigation'), ['pages' => 1])->assertNotFound();
        $this->expectException(HttpException::class);
        app(NavigationSimulation::class)->render($this->user(), '/dashboard');
    }
}
