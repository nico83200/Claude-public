<?php

namespace Tests\Feature;

use App\Models\HorseAccessGrant;
use App\Models\HorseMeasurement;
use App\Notifications\AppNotification;
use App\Services\Entitlements;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Poids et état corporel : pesées, estimation au ruban, alertes de variation. */
class WeightTest extends TestCase
{
    public function test_weighings_tape_estimate_chart_and_validation(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->get("/chevaux/{$horse->id}/poids")->assertOk()->assertSee('Aucune mesure pour le moment')->assertSee('>Poids<', false);

        $this->actingAs($user)->post("/chevaux/{$horse->id}/poids", ['measured_on' => '2026-08-01', 'method' => 'scale', 'weight_kg' => '520', 'body_condition' => 5])->assertSessionHas('success');
        // Ruban : 190 cm de tour de poitrine × 173 cm de longueur → 190² × 173 / 11 877 ≈ 525,8 kg.
        $this->actingAs($user)->post("/chevaux/{$horse->id}/poids", ['measured_on' => '2026-09-01', 'method' => 'tape', 'girth_cm' => 190, 'length_cm' => 173])
            ->assertSessionHas('success', 'Mesure enregistrée : poids estimé à 526 kg.');
        $this->assertSame(525.8, HorseMeasurement::latest('id')->first()->weight_kg);

        $this->actingAs($user)->get("/chevaux/{$horse->id}/poids")->assertOk()->assertSee('weightChart(', false)->assertSee('525,8 kg')->assertSee('+5,8 kg')->assertSee('520 kg');

        // Ni poids ni note : refusé ; ruban incomplet : refusé ; date future : refusée.
        $this->actingAs($user)->post("/chevaux/{$horse->id}/poids", ['measured_on' => '2026-09-02', 'method' => 'scale'])->assertSessionHasErrors('weight_kg');
        $this->actingAs($user)->post("/chevaux/{$horse->id}/poids", ['measured_on' => '2026-09-02', 'method' => 'tape', 'girth_cm' => 190])->assertSessionHasErrors('length_cm');
        $this->actingAs($user)->post("/chevaux/{$horse->id}/poids", ['measured_on' => now()->addDay()->toDateString(), 'method' => 'scale', 'weight_kg' => 500])->assertSessionHasErrors('measured_on');
        $this->assertSame(2, HorseMeasurement::count());
    }

    public function test_significant_variation_alerts_the_horse_managers(): void
    {
        Notification::fake();
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view', 'dailylog.create']]);

        $this->actingAs($rider)->post("/chevaux/{$horse->id}/poids", ['measured_on' => '2026-08-01', 'method' => 'scale', 'weight_kg' => 520, 'body_condition' => 5])->assertSessionHas('success');
        Notification::assertNothingSent();

        // −6 % : alerte au propriétaire (pas à l'auteur de la mesure).
        $this->actingAs($rider)->post("/chevaux/{$horse->id}/poids", ['measured_on' => '2026-09-01', 'method' => 'scale', 'weight_kg' => 488.8, 'body_condition' => 3])
            ->assertSessionHas('warning');
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => str_contains($n->body, 'Perte de 31,2 kg (6,0 %)') && str_contains($n->body, 'de 5 à 3') && $n->url === route('horses.weight', $horse));
        Notification::assertNotSentTo($rider, AppNotification::class);
    }

    public function test_access_rules(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view']]);
        $this->actingAs($viewer)->get("/chevaux/{$horse->id}/poids")->assertOk()->assertDontSee('Nouvelle mesure');
        $this->actingAs($viewer)->post("/chevaux/{$horse->id}/poids", ['measured_on' => '2026-08-01', 'method' => 'scale', 'weight_kg' => 500])->assertForbidden();
        $this->actingAs($this->user())->get("/chevaux/{$horse->id}/poids")->assertForbidden();

        $this->actingAs($owner)->post("/chevaux/{$horse->id}/poids", ['measured_on' => '2026-08-01', 'method' => 'scale', 'weight_kg' => 500]);
        $m = HorseMeasurement::first();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        app(Entitlements::class)->flush();
        $other = $this->horseFor($owner);
        $this->actingAs($owner)->delete("/chevaux/{$other->id}/poids/{$m->id}")->assertNotFound();
        $this->actingAs($owner)->delete("/chevaux/{$horse->id}/poids/{$m->id}")->assertSessionHas('success');
    }
}
