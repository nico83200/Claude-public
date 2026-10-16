<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\HealthObservation;
use App\Models\HorseAccessGrant;
use App\Models\Treatment;
use App\Support\Perm;
use Tests\TestCase;

/** Plusieurs chevaux (lot E) : accueil « Tous les chevaux ». */
class Version229RequestsTest extends TestCase
{
    private function owner(): array
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        $jack = $this->horseFor($user, null, ['official_name' => 'JACKPOT', 'usual_name' => 'Jack']);
        $uni = $this->horseFor($user, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $cap = $this->horseFor($user, null, ['official_name' => 'CAPUCINE']);
        $this->allHorses($user);

        return [$user, $jack, $uni, $cap];
    }

    public function test_each_horse_has_its_state_and_one_tap_all_good(): void
    {
        [$user, $jack, $uni, $cap] = $this->owner();
        HealthObservation::forceCreate(['horse_id' => $uni->id, 'author_id' => $user->id, 'severity' => 'alert', 'body' => 'Œil gonflé', 'observed_at' => now()]);
        DailyLog::create(['horse_id' => $cap->id, 'author_id' => $user->id, 'logged_at' => now(), 'appetite' => 'good', 'general_state' => 'good']);

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard')->assertSee('data-stable-today', false);
        foreach ([$jack, $uni, $cap] as $h) {
            $page->assertSee('data-stable-horse="'.$h->id.'"', false);
        }
        $page->assertSee('data-horse-alert', false)->assertSee('Œil gonflé')
            ->assertSee('data-horse-all-good="'.$jack->id.'"', false)->assertDontSee('data-horse-all-good="'.$cap->id.'"', false)
            ->assertSee('Tout va bien pour tous (2)');

        // Un cheval : retour sur l'accueil « Tous les chevaux ».
        $this->actingAs($user)->from(route('dashboard'))->post(route('horses.logs.store', $jack), ['appetite' => 'good', 'general_state' => 'good', 'all_good' => '1'])->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('dashboard'))->assertViewIs('dashboard')->assertDontSee('data-all-good-all', false);
    }

    public function test_all_good_for_all_skips_done_and_unauthorised_horses(): void
    {
        [$user, $jack, $uni, $cap] = $this->owner();
        DailyLog::create(['horse_id' => $cap->id, 'author_id' => $user->id, 'logged_at' => now(), 'appetite' => 'good', 'general_state' => 'good']);
        $foreign = $this->horseFor($this->user());

        $this->actingAs($user)->from(route('dashboard'))->post(route('logs.all-good'), ['horses' => [$jack->id, $uni->id, $cap->id, $foreign->id]])
            ->assertRedirect(route('dashboard'))->assertSessionHas('success', 'Tout va bien noté pour 2 chevaux.');
        $this->assertSame(1, DailyLog::where('horse_id', $jack->id)->count());
        $this->assertSame(1, DailyLog::where('horse_id', $uni->id)->count());
        $this->assertSame(1, DailyLog::where('horse_id', $cap->id)->count());
        $this->assertSame(0, DailyLog::where('horse_id', $foreign->id)->count());

        $this->actingAs($user)->post(route('logs.all-good'), ['horses' => [$jack->id]])->assertSessionHas('success', 'Le suivi du jour était déjà noté.');
    }

    public function test_treatments_of_all_horses_are_grouped_with_administered(): void
    {
        [$user, $jack, $uni] = $this->owner();
        $mk = fn ($h, $product, $extra = []) => Treatment::forceCreate($extra + ['horse_id' => $h->id, 'author_id' => $user->id, 'product' => $product, 'starts_on' => now()->subDay()->toDateString(), 'status' => 'active', 'track_administrations' => true]);
        $a = $mk($jack, 'Equipalazone');
        $b = $mk($uni, 'Collyre');

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-stable-treatments', false)
            ->assertSee('data-stable-treatment="'.$a->id.'"', false)->assertSee('data-stable-treatment="'.$b->id.'"', false)->assertSee('Administré');
        $this->actingAs($user)->from(route('dashboard'))->post(route('horses.treatments.administer', [$jack, $a]))->assertRedirect(route('dashboard'));
        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();
        $this->assertMatchesRegularExpression('#data-stable-treatment="'.$a->id.'".*?Fait#s', $html);

        // Demi-pensionnaire : « Administré » seulement pour le traitement dont elle est responsable.
        $rider = $this->user();
        foreach ([$jack, $uni] as $h) {
            HorseAccessGrant::create(['horse_id' => $h->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW, Perm::TREATMENTS_VIEW, Perm::DAILYLOG_CREATE]]);
        }
        $b->update(['responsible_user_id' => $rider->id]);
        $this->allHorses($rider);
        $html = $this->actingAs($rider)->get(route('dashboard'))->assertOk()->getContent();
        preg_match('#data-stable-treatment="'.$b->id.'".*?</div>#s', $html, $mb);
        preg_match('#data-stable-treatment="'.$a->id.'".*?</div>#s', $html, $ma);
        $this->assertStringContainsString('Administré', $mb[0]);
        $this->assertStringNotContainsString('Administré', $ma[0]);
    }
}
