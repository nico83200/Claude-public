<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\DailyLog;
use App\Models\HorseAccessGrant;
use App\Models\Professional;
use App\Services\HorseAccess;
use App\Support\Perm;
use Tests\TestCase;

/** Mode « un cheval » (lot C) : passage à plusieurs chevaux et parcours complet. */
class Version227RequestsTest extends TestCase
{
    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
    }

    public function test_switching_between_one_and_several_horses_is_explained_once(): void
    {
        $user = $this->user();
        $this->horseFor($user, null, ['official_name' => 'JACK', 'usual_name' => 'Jack']);
        // Première visite : l'état est mémorisé, sans message.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('data-solo-transition', false);

        // Un cheval partagé arrive : message une seule fois ; le cheval actif reste le sien.
        $other = $this->user();
        $uni = $this->horseFor($other, null, ['official_name' => 'UNI']);
        $grant = HorseAccessGrant::create(['horse_id' => $uni->id, 'user_id' => $user->id, 'permissions' => [Perm::HORSE_VIEW]]);
        $this->fresh();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard-solo')->assertSee('data-solo-transition="multi"', false)
            ->assertSee('data-focus-switch', false);
        $this->fresh();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('data-solo-transition', false);

        // Le partage prend fin : retour au tableau de bord du cheval, expliqué une fois.
        $grant->update(['revoked_at' => now()]);
        $this->fresh();
        $this->actingAs($user)->get(route('dashboard'))->assertViewIs('dashboard-solo')->assertSee('data-solo-transition="solo"', false)
            ->assertSee('tableau de bord de Jack');
        $this->fresh();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('data-solo-transition', false);
    }

    public function test_single_horse_journey_without_the_horse_list(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user, null, ['official_name' => 'JACK', 'usual_name' => 'Jack']);
        $farrier = CareCategory::where('key', 'farrier')->firstOrFail();
        $care = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $farrier->id, 'author_id' => $user->id, 'performed_at' => now()->subWeeks(7), 'next_check_on' => now()->subDay()->toDateString()]);

        $home = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-solo-state="amber"', false)
            ->assertSee('Rechercher un soin, une séance…');
        $this->assertStringNotContainsString('href="'.route('horses.index').'" class="flex min-h-11', $home->getContent());

        // 1 toucher : tout va bien.
        $this->actingAs($user)->from(route('dashboard'))->post(route('horses.logs.store', $horse), ['appetite' => 'good', 'general_state' => 'good', 'all_good' => '1'])->assertRedirect(route('dashboard'));
        $this->assertSame(1, DailyLog::where('horse_id', $horse->id)->count());

        // « Noter le passage » depuis l'accueil : formulaire prérempli, puis l'échéance est close.
        $form = $this->actingAs($user)->get(route('horses.care.create', [$horse, 'suite' => $care->id]))->assertOk();
        $this->assertSame($farrier->id, $form->viewData('record')->care_category_id);
        $this->actingAs($user)->post(route('horses.care.store', $horse), ['care_category_id' => $farrier->id, 'performed_at' => now()->format('Y-m-d\TH:i'), 'next_mode' => 'none'])->assertRedirect();
        $this->assertNotNull($care->fresh()->next_check_done_at);
        $this->fresh();
        $this->actingAs($user)->get(route('dashboard'))->assertSee('data-solo-state="green"', false)->assertDontSee('data-solo-due="'.$care->id.'"', false);

        // Menu : « Fiche de Jack » actif sur sa fiche, « Santé » actif dans le dossier santé.
        $this->assertMatchesRegularExpression('#aria-current="page"\s+data-nav="horses.show"#', $this->actingAs($user)->get(route('horses.show', $horse))->getContent());
        $this->assertMatchesRegularExpression('#aria-current="page"\s+data-nav="health.index"#', $this->actingAs($user)->get(route('horses.care.index', $horse))->getContent());
        $this->assertMatchesRegularExpression('#aria-current="page"\s+data-nav="health.index"#', $this->actingAs($user)->get(route('health.index', ['onglet' => 'poids']))->getContent());
    }

    public function test_health_chip_offers_an_appointment_with_the_last_visit_as_a_reminder(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user, null, ['official_name' => 'JACK', 'usual_name' => 'Jack']);
        $pro = Professional::forceCreate(['organization_id' => $horse->organization_id, 'first_name' => 'Julien', 'last_name' => 'Moreau', 'kind' => 'farrier']);
        $farrier = CareCategory::where('key', 'farrier')->firstOrFail();
        $care = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $farrier->id, 'professional_id' => $pro->id, 'author_id' => $user->id,
            'performed_at' => '2026-10-22 10:00', 'next_check_on' => now()->addWeeks(6)->toDateString()]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-solo-check-sheet="'.$care->id.'"', false)
            ->assertSee('dernier passage le <strong>22 octobre 2026</strong> avec Julien Moreau', false)->assertSee('data-solo-check-plan', false);

        // Rendez-vous pris un autre jour que l'échéance : l'agenda et le prochain passage suivent, la note garde le passage précédent.
        $day = now()->addWeeks(5)->startOfWeek()->addDays(1);
        $this->actingAs($user)->from(route('dashboard'))->post(route('horses.care.check.plan', [$horse, $care]), ['date' => $day->toDateString(), 'time' => '14:30'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('success', fn ($m) => str_contains($m, 'à 14:30'));
        $event = CalendarEvent::where('source_id', $care->id)->sole();
        $this->assertSame($day->toDateString().' 14:30', $event->starts_at->format('Y-m-d H:i'));
        $this->assertSame('Pour mémoire : dernier passage le 22/10/2026 avec Julien Moreau.', $event->notes);
        $this->assertSame($day->toDateString(), $care->fresh()->next_check_on->toDateString());
        $this->actingAs($user)->get(route('dashboard'))->assertSee('RDV le '.$day->translatedFormat('j M'))->assertSee(route('calendar.show', $event), false);

        // Date passée refusée.
        $this->actingAs($user)->post(route('horses.care.check.plan', [$horse, $care]), ['date' => now()->subDay()->toDateString()])->assertSessionHasErrors('date');
    }
}
