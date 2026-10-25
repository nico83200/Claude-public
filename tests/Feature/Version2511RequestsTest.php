<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\HealthObservation;
use App\Services\HorseAccess;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** « Rien à signaler » : journée sans histoire en un geste, masqué tant qu'un point d'attention est en cours. */
class Version2511RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_nothing_to_report_in_one_tap_hidden_while_a_point_is_open(): void
    {
        Carbon::setTestNow('2026-10-25 10:00');
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);

        $this->actingAs($owner)->get(route('dashboard'))->assertSee('data-solo-nothing', false)->assertSee('Rien à signaler')->assertDontSee('Tout va bien aujourd\'hui');
        $this->actingAs($owner)->get(route('horses.logs.index', $horse))->assertSee('data-log-nothing', false);

        // Un point d'attention en cours, même une simple remarque : plus de « Rien à signaler ».
        $point = HealthObservation::forceCreate(['horse_id' => $horse->id, 'author_id' => $owner->id, 'severity' => 'info', 'body' => 'Petite égratignure', 'observed_at' => now()]);
        app()->forgetInstance(HorseAccess::class);
        $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('data-solo-nothing', false);
        $this->actingAs($owner)->get(route('horses.logs.index', $horse))->assertDontSee('data-log-nothing', false);

        // Clos : le bouton revient ; un geste note le suivi du jour.
        $point->update(['resolved_at' => now()]);
        $this->actingAs($owner)->get(route('dashboard'))->assertSee('data-solo-nothing', false);
        $this->actingAs($owner)->from(route('dashboard'))->post(route('horses.logs.store', $horse), ['appetite' => 'good', 'general_state' => 'good', 'all_good' => '1'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('success', 'C\'est noté : rien à signaler aujourd\'hui.');
        $this->assertSame(['good', 'good'], [DailyLog::sole()->appetite, DailyLog::sole()->general_state]);
        $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('data-solo-nothing', false)->assertSee('data-solo-log-done', false)->assertSee('data-solo-state="green"', false);
    }
}
