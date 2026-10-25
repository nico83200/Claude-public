<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\HealthObservation;
use App\Services\AttentionPoints;
use App\Services\HorseAccess;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Le suivi du jour se complète d'après « Quelque chose d'inhabituel ? » : ouverture, nouvelles, clôture. */
class Version252RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
        app()->forgetInstance(AttentionPoints::class);
    }

    private function setUpHorse(): array
    {
        Carbon::setTestNow('2026-10-25 09:00');
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');

        return [$owner, $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni'])];
    }

    public function test_opening_a_point_fills_the_daily_log_and_news_complete_it(): void
    {
        [$owner, $horse] = $this->setUpHorse();
        $this->actingAs($owner)->post(route('horses.observations.store', $horse), ['title' => 'Saignement de nez', 'body' => 'Revenu pendant la monte après nettoyage.', 'severity' => 'watch']);
        $point = HealthObservation::sole();
        $log = DailyLog::sole();
        $this->assertSame([$point->id, 'average', null], [$log->health_observation_id, $log->general_state, $log->appetite]);
        $this->assertSame('Saignement de nez : Revenu pendant la monte après nettoyage.', $log->anomalies);
        $this->fresh();
        $this->actingAs($owner)->get(route('dashboard'))->assertSee('data-solo-log-done', false)->assertDontSee('data-solo-nothing', false);

        // Nouvelles du jour : ajoutées au même suivi, sans doublon.
        Carbon::setTestNow('2026-10-25 18:00');
        $this->fresh();
        $this->actingAs($owner)->post(route('horses.observations.updates.store', [$horse, $point]), ['trend' => 'worse', 'body' => 'Encore un peu de sang.']);
        $this->actingAs($owner)->post(route('horses.observations.updates.store', [$horse, $point]), ['trend' => 'worse', 'body' => 'Encore un peu de sang.']);
        $this->assertSame(1, DailyLog::count());
        $this->assertSame("Saignement de nez : Revenu pendant la monte après nettoyage.\nSaignement de nez : ça s'aggrave – Encore un peu de sang.", DailyLog::sole()->anomalies);

        // Le lendemain : un nouveau suivi du jour ; la clôture y est notée.
        Carbon::setTestNow('2026-10-26 10:00');
        $this->fresh();
        $this->actingAs($owner)->post(route('horses.observations.updates.store', [$horse, $point]), ['trend' => 'better']);
        $this->actingAs($owner)->post(route('horses.observations.resolve', [$horse, $point]), ['note' => 'Plus rien depuis hier soir.']);
        $today = DailyLog::latest('logged_at')->first();
        $this->assertSame(2, DailyLog::count());
        $this->assertSame(['Saignement de nez : ça va mieux', 'Saignement de nez : rentré dans l\'ordre – Plus rien depuis hier soir.'], [$today->anomalies, $today->observations]);
        $this->fresh();
        $this->actingAs($owner)->get(route('horses.logs.index', $horse))->assertSee('data-log-attention-link', false)->assertSee(route('horses.observations.show', [$horse, $point]), false);
    }

    public function test_point_after_nothing_to_report_completes_the_same_log(): void
    {
        [$owner, $horse] = $this->setUpHorse();
        $this->actingAs($owner)->post(route('horses.logs.store', $horse), ['appetite' => 'good', 'general_state' => 'good', 'all_good' => '1']);
        $this->actingAs($owner)->post(route('horses.observations.store', $horse), ['body' => 'Petite égratignure au boulet', 'severity' => 'info']);
        $log = DailyLog::sole();
        $this->assertSame(['good', 'good', 'Petite égratignure au boulet'], [$log->appetite, $log->general_state, $log->anomalies], 'Simple remarque : l\'état reste celui noté.');

        $this->actingAs($owner)->post(route('horses.observations.store', $horse), ['body' => 'Boiterie au pas', 'severity' => 'alert']);
        $log->refresh();
        $this->assertSame(['average', "Petite égratignure au boulet\nBoiterie au pas"], [$log->general_state, $log->anomalies]);
        $this->assertSame(HealthObservation::latest('id')->value('id'), $log->health_observation_id);
        // Jamais remonté, jamais « mauvais » d'office.
        $log->update(['general_state' => 'poor']);
        $this->fresh();
        $this->actingAs($owner)->post(route('horses.observations.updates.store', [$horse, HealthObservation::latest('id')->first()]), ['trend' => 'worse']);
        $this->assertSame('poor', $log->fresh()->general_state);
    }
}
