<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\HealthObservation;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Reprise : les observations passées servent de suivi du jour où elles ont été signalées. */
class Version2521RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_past_observations_fill_the_daily_log_of_their_day_once(): void
    {
        Carbon::setTestNow('2026-10-26 10:00');
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);

        // 20 octobre : un suivi « bon » existait déjà, puis une observation « À surveiller ».
        DailyLog::create(['horse_id' => $horse->id, 'author_id' => $owner->id, 'logged_at' => '2026-10-20 08:00', 'appetite' => 'good', 'general_state' => 'good']);
        HealthObservation::forceCreate(['horse_id' => $horse->id, 'author_id' => $owner->id, 'severity' => 'watch', 'body' => 'Boiterie au pas', 'observed_at' => '2026-10-20 17:30']);
        // 22 octobre : aucune saisie ce jour-là, une simple remarque (résolue depuis).
        HealthObservation::forceCreate(['horse_id' => $horse->id, 'author_id' => $owner->id, 'severity' => 'info', 'body' => 'Petite égratignure', 'observed_at' => '2026-10-22 11:15', 'resolved_at' => '2026-10-23 09:00']);

        $this->artisan('attention:backfill-daily-logs', ['--dry-run' => true])->expectsOutputToContain('Simulation : 2 suivi(s) du jour créé(s), 1 complété(s), 0 ligne(s) déjà présente(s).')->assertSuccessful();
        $this->assertSame(1, DailyLog::count(), 'La simulation n\'enregistre rien.');

        $this->artisan('attention:backfill-daily-logs')->expectsOutputToContain('2 suivi(s) du jour créé(s), 1 complété(s)')->assertSuccessful();
        $first = DailyLog::whereDate('logged_at', '2026-10-20')->sole();
        $this->assertSame(['good', 'average', 'Boiterie au pas'], [$first->appetite, $first->general_state, $first->anomalies]);
        $second = DailyLog::whereDate('logged_at', '2026-10-22')->sole();
        $this->assertSame(['2026-10-22 11:15', null, 'Petite égratignure', $owner->id], [$second->logged_at->format('Y-m-d H:i'), $second->general_state, $second->anomalies, $second->author_id]);
        $this->assertSame(HealthObservation::where('body', 'Petite égratignure')->value('id'), $second->health_observation_id);
        // La clôture (« Marquer traité » d'avant) rejoint le suivi de son propre jour.
        $this->assertSame('Petite égratignure : rentré dans l\'ordre', DailyLog::whereDate('logged_at', '2026-10-23')->sole()->observations);

        // Relancée : rien en double.
        $this->artisan('attention:backfill-daily-logs')->expectsOutputToContain('0 suivi(s) du jour créé(s), 0 complété(s), 3 ligne(s) déjà présente(s).');
        $this->assertSame(3, DailyLog::count());
        $this->assertSame('Boiterie au pas', $first->fresh()->anomalies);
        $this->actingAs($owner)->get(route('horses.logs.index', $horse))->assertSee('Petite égratignure')->assertSee('data-log-attention-link', false);
    }

    /** Cas remonté : observation ouverte hier, nouvelle donnée aujourd'hui avant la mise à jour → un suivi chaque jour. */
    public function test_news_given_another_day_fill_the_log_of_that_day(): void
    {
        Carbon::setTestNow('2026-10-26 18:00');
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $point = HealthObservation::forceCreate(['horse_id' => $horse->id, 'author_id' => $owner->id, 'severity' => 'watch', 'title' => 'Saignement de nez', 'body' => 'Revenu pendant la monte.', 'observed_at' => '2026-10-25 09:00']);
        // Suivi d'hier déjà repris par la première reprise ; nouvelle d'aujourd'hui donnée avant la mise à jour (sans suivi).
        DailyLog::create(['horse_id' => $horse->id, 'author_id' => $owner->id, 'logged_at' => '2026-10-25 09:00', 'general_state' => 'average', 'anomalies' => 'Saignement de nez : Revenu pendant la monte.', 'health_observation_id' => $point->id]);
        $point->updates()->forceCreate(['author_id' => $owner->id, 'trend' => 'better', 'body' => 'Plus de sang ce matin.', 'created_at' => '2026-10-26 08:30', 'updated_at' => '2026-10-26 08:30']);

        $this->artisan('attention:backfill-daily-logs')->expectsOutputToContain('1 suivi(s) du jour créé(s), 0 complété(s), 1 ligne(s) déjà présente(s).');
        $today = DailyLog::whereDate('logged_at', '2026-10-26')->sole();
        $this->assertSame(['Saignement de nez : ça va mieux – Plus de sang ce matin.', '08:30', $point->id], [$today->anomalies, $today->logged_at->format('H:i'), $today->health_observation_id]);
        $this->assertSame(2, DailyLog::count());
    }
}
