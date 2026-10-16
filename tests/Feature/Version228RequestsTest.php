<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Services\HorseAccess;
use App\Services\OrganizationService;
use Tests\TestCase;

/** Plusieurs chevaux (lot D) : cheval actif, choix en tête du menu, rendez-vous groupés. */
class Version228RequestsTest extends TestCase
{
    private function owner(): array
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        $jack = $this->horseFor($user, null, ['official_name' => 'JACKPOT', 'usual_name' => 'Jack']);
        $uni = $this->horseFor($user, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);

        return [$user, $jack, $uni];
    }

    private function fresh($user): void
    {
        $user->unsetRelation('profile');
        app()->forgetInstance(HorseAccess::class);
    }

    public function test_owner_lands_on_a_horse_and_switches_from_the_menu(): void
    {
        [$user, $jack, $uni] = $this->owner();

        // Par défaut : un cheval (le premier par nom d'usage), menu du cheval avec le choix du cheval.
        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard-solo');
        $this->assertTrue($page->viewData('horse')->is($jack));
        $page->assertSee('data-focus-switch', false)->assertSee('data-focus-choice="'.$uni->id.'"', false)->assertSee('data-focus-choice="all"', false)
            ->assertSee('Fiche de Jack')->assertSee('href="'.route('budget.index', ['horse' => $jack->id]).'"', false)->assertSee('data-solo-switch', false);
        // Santé : le dossier du cheval actif.
        $this->actingAs($user)->get(route('health.index'))->assertOk()->assertSee('Jack · soins et traitements');

        // Choix d'Uni : mémorisé (il reste actif à la prochaine ouverture).
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $uni->id])->assertRedirect(route('dashboard'));
        $this->assertSame($uni->id, $user->profile()->value('active_horse_id'));
        $this->fresh($user);
        $this->flushSession();
        $this->assertTrue($this->actingAs($user)->get(route('dashboard'))->viewData('horse')->is($uni));

        // « Tous les chevaux » : accueil de tous les chevaux, menu habituel.
        $this->actingAs($user)->post(route('focus.set'), ['horse' => 'all'])->assertRedirect(route('dashboard'));
        $this->fresh($user);
        $this->actingAs($user)->get(route('dashboard'))->assertViewIs('dashboard')->assertSee('Tous les chevaux');

        // Ouvrir le dossier d'un cheval en fait le cheval actif (dernier cheval consulté).
        $this->actingAs($user)->get(route('horses.show', $jack))->assertOk();
        $this->fresh($user);
        $this->assertTrue($this->actingAs($user)->get(route('dashboard'))->viewData('horse')->is($jack));

        // Le cheval d'un autre ne peut pas être choisi.
        $stranger = $this->horseFor($this->user());
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $stranger->id])->assertNotFound();
    }

    public function test_forms_preselect_the_active_horse_but_keep_the_choice(): void
    {
        [$user, $jack, $uni] = $this->owner();
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $uni->id]);
        $this->fresh($user);
        $html = $this->actingAs($user)->get(route('sessions.create'))->assertOk()->assertDontSee('data-horse-implicit', false)->getContent();
        $this->assertMatchesRegularExpression('#<option value="'.$uni->id.'" selected>#', $html);
        $this->assertStringContainsString('<option value="'.$jack->id.'"', $html);
    }

    public function test_stable_members_start_with_all_horses(): void
    {
        $manager = $this->user();
        $stable = app(OrganizationService::class)->createStable($manager, ['name' => 'Écurie du Lac']);
        $this->grantLicense($stable, 'ecurie');
        $a = $this->horseFor($manager, $stable, ['official_name' => 'ALPHA']);
        $this->horseFor($manager, $stable, ['official_name' => 'BRAVO']);
        $this->fresh($manager);

        $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard');
        // Choix d'un cheval : pour la session seulement (pas mémorisé sur le profil).
        $this->actingAs($manager)->post(route('focus.set'), ['horse' => $a->id])->assertSessionHas('focus_horse', $a->id);
        $this->fresh($manager);
        $this->assertTrue($this->actingAs($manager)->withSession(['focus_horse' => $a->id])->get(route('dashboard'))->viewData('horse')->is($a));
        $this->assertNull($manager->profile()->value('active_horse_id'));
        $this->flushSession();
        $this->fresh($manager);
        $this->actingAs($manager)->get(route('dashboard'))->assertViewIs('dashboard');
    }

    public function test_grouped_appointment_for_several_horses(): void
    {
        [$user, $jack, $uni] = $this->owner();
        $farrier = CareCategory::where('key', 'farrier')->firstOrFail();
        $mk = fn ($horse, $due) => CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $farrier->id, 'author_id' => $user->id,
            'performed_at' => now()->subWeeks(6), 'next_check_on' => $due]);
        $jackCare = $mk($jack, now()->addDays(10)->toDateString());
        $uniCare = $mk($uni, now()->addDays(14)->toDateString());

        // Depuis la pastille de Jack : Uni proposé (même échéance proche), coché d'office.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-solo-group="'.$jackCare->id.'"', false)
            ->assertSee('name="with[]" value="'.$uniCare->id.'" class="size-5 rounded border-slate-300 text-brand-700" checked', false);

        $day = now()->addDays(12)->setTime(10, 0);
        $stranger = $this->user();
        $foreign = CareRecord::forceCreate(['horse_id' => $this->horseFor($stranger)->id, 'care_category_id' => $farrier->id, 'author_id' => $stranger->id, 'performed_at' => now()->subWeeks(6), 'next_check_on' => now()->addDays(12)->toDateString()]);
        $this->actingAs($user)->post(route('horses.care.check.plan', [$jack, $jackCare]), ['date' => $day->toDateString(), 'time' => '10:00', 'with' => [$uniCare->id, $foreign->id]])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Rendez-vous groupé pour Jack, Uni'));

        $events = CalendarEvent::whereIn('source_id', [$jackCare->id, $uniCare->id, $foreign->id])->get();
        $this->assertCount(2, $events, 'Le cheval d\'un autre n\'est pas ajouté.');
        $this->assertSame(1, $events->pluck('group_uuid')->unique()->count());
        $this->assertNotNull($events->first()->group_uuid);
        $this->assertSame([$day->format('Y-m-d H:i')], $events->map(fn ($e) => $e->starts_at->format('Y-m-d H:i'))->unique()->values()->all());
        $this->assertSame($day->toDateString(), $uniCare->fresh()->next_check_on->toDateString());

        // Fiche du rendez-vous : l'autre cheval est cité ; déplacer l'un déplace l'autre (si demandé).
        $jackEvent = $events->firstWhere('source_id', $jackCare->id);
        $uniEvent = $events->firstWhere('source_id', $uniCare->id);
        $this->actingAs($user)->get(route('calendar.show', $jackEvent))->assertOk()->assertSee('data-event-group', false)->assertSee(route('calendar.show', $uniEvent), false);
        $this->actingAs($user)->get(route('calendar.edit', $jackEvent))->assertOk()->assertSee('data-whole-group', false);
        $later = now()->addDays(15);
        $this->actingAs($user)->put(route('calendar.update', $jackEvent), ['type' => $jackEvent->type, 'title' => $jackEvent->title, 'horse_id' => $jack->id,
            'date' => $later->toDateString(), 'time' => '08:30', 'status' => 'planned', 'whole_group' => '1'])->assertSessionHas('success', fn ($m) => str_contains($m, 'autre cheval'));
        $this->assertSame($later->toDateString().' 08:30', $uniEvent->fresh()->starts_at->format('Y-m-d H:i'));

        // Sans la case : seul l'événement modifié bouge.
        $this->actingAs($user)->put(route('calendar.update', $jackEvent), ['type' => $jackEvent->type, 'title' => $jackEvent->title, 'horse_id' => $jack->id,
            'date' => $later->toDateString(), 'time' => '11:00', 'status' => 'planned']);
        $this->assertSame('08:30', $uniEvent->fresh()->starts_at->format('H:i'));
    }
}
