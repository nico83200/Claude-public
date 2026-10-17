<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\DailyLog;
use App\Models\Horse;
use App\Models\License;
use App\Models\Organization;
use App\Models\RidingSession;
use App\Models\SimulationBatch;
use App\Models\User;
use App\Services\LoadSimulation;
use App\Services\WeeklyDigest;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
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
        // Indicateurs : comptes de test exclus.
        $this->assertSame(User::whereNull('simulation_batch_id')->count(), $this->as($admin)->get(route('admin.dashboard'))->viewData('counts')['users']);

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
}
