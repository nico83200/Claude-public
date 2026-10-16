<?php

namespace Tests\Feature;

use App\Models\HorseAccessGrant;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Mode « un cheval » (lot A) : menu du cheval, barre du bas, cheval choisi d'office. */
class Version225RequestsTest extends TestCase
{
    private function nav(string $html): string
    {
        return Str::betweenFirst($html, 'aria-label="Navigation principale"', '</nav>');
    }

    public function test_single_horse_menu_is_the_horse_menu(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $jack = $this->horseFor($user, null, ['official_name' => 'JACK', 'usual_name' => 'Jack']);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
        $nav = $this->nav($html);
        $this->assertStringContainsString('data-solo-horse-card', $nav);
        foreach (['Tableau de bord', 'Agenda', 'Séances', 'Santé', 'Budget', 'Documents', 'Intervenants', 'Partage', 'Fiche de Jack'] as $label) {
            $this->assertStringContainsString($label, $nav, $label);
        }
        foreach (['Ma semaine', 'Exercices', '>Chevaux<'] as $gone) {
            $this->assertStringNotContainsString($gone, $nav, $gone);
        }
        $this->assertStringContainsString('href="'.route('horses.sharing', $jack).'"', $nav);
        $this->assertStringContainsString('href="'.route('horses.documents.index', $jack).'"', $nav);
        // « Mes chevaux » reste accessible (ajouter un cheval, chevaux archivés).
        $this->assertStringContainsString('href="'.route('horses.index').'"', $html);

        // Barre du bas : Accueil · Séances · micro · Santé · Menu.
        $bar = Str::betweenFirst($html, 'aria-label="Navigation rapide"', '</nav>');
        $this->assertSame(['dashboard', 'sessions.index', 'health.index'], array_map(fn ($m) => $m, preg_match_all('/data-bar="([^"]+)"/', $bar, $m) ? $m[1] : []));

        // Séances : la bibliothèque d'exercices y est rangée.
        $this->actingAs($user)->get(route('sessions.index'))->assertOk()->assertSee('data-exercises-link', false);
        // Fiche : retour vers l'accueil, plus vers la liste des chevaux.
        $this->actingAs($user)->get(route('horses.show', $jack))->assertOk()->assertSee('href="'.route('dashboard').'" class="mb-3', false);
    }

    public function test_discovery_offer_shows_locked_features_with_the_symbol(): void
    {
        $user = $this->user();
        $this->horseFor($user, null, ['official_name' => 'JACK']);
        $nav = $this->nav($this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent());
        foreach (['budget.index', 'horses.documents.index'] as $entry) {
            $this->assertMatchesRegularExpression('#data-nav="'.preg_quote($entry).'".*?</a>#s', $nav);
            preg_match('#data-nav="'.preg_quote($entry).'".*?</a>#s', $nav, $m);
            $this->assertStringContainsString('(offre supérieure)', $m[0], $entry);
        }
        preg_match('#data-nav="calendar.index".*?</a>#s', $nav, $m);
        $this->assertStringNotContainsString('(offre supérieure)', $m[0]);
    }

    public function test_health_menu_opens_the_horse_health_record(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $jack = $this->horseFor($user, null, ['official_name' => 'JACK', 'usual_name' => 'Jack']);
        $this->actingAs($user)->get(route('health.index'))->assertOk()->assertSee('data-health-tabs', false)
            ->assertSee('Jack · soins et traitements')->assertSee(route('horses.care.create', $jack), false)->assertDontSee('data-health-horses', false);
    }

    public function test_horse_choice_is_hidden_and_preset_everywhere(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        $jack = $this->horseFor($user);

        $budget = $this->actingAs($user)->get(route('budget.index'))->assertOk();
        $budget->assertSee('data-horse-implicit="'.$jack->id.'"', false)->assertDontSee('id="f_horse"', false);
        $this->actingAs($user)->get(route('sessions.create'))->assertOk()->assertSee('data-horse-implicit="'.$jack->id.'"', false);
        $calendar = $this->actingAs($user)->get(route('calendar.index'))->assertOk()->assertSee('data-horse-implicit="'.$jack->id.'"', false)->getContent();
        $this->assertStringNotContainsString('name="horse"', $calendar);

        // Le formulaire envoie bien le cheval.
        $this->actingAs($user)->post(route('calendar.store'), ['type' => 'competition', 'title' => 'Concours', 'horse_id' => $jack->id, 'date' => now()->addDay()->toDateString(), 'time' => '10:00'])->assertSessionHasNoErrors();

        // Deux chevaux : retour des choix.
        $this->horseFor($user);
        app()->forgetInstance(HorseAccess::class);
        $this->actingAs($user)->get(route('sessions.create'))->assertOk()->assertDontSee('data-horse-implicit', false);
        $this->actingAs($user)->get(route('budget.index'))->assertOk()->assertSee('id="f_horse"', false);
        $nav = $this->nav($this->actingAs($user)->get(route('dashboard'))->getContent());
        $this->assertStringContainsString('data-focus-switch', $nav);
        $this->allHorses($user);
        $nav = $this->nav($this->actingAs($user)->get(route('dashboard'))->getContent());
        $this->assertStringNotContainsString('data-solo-horse-card', $nav);
        $this->assertStringContainsString('Chevaux', $nav);
    }

    public function test_half_lease_rider_with_one_horse_sees_only_what_was_shared(): void
    {
        $owner = $this->user();
        $jack = $this->horseFor($owner, null, ['official_name' => 'JACK']);
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $jack->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW, Perm::SESSIONS_VIEW, Perm::SESSIONS_CREATE, Perm::CALENDAR_VIEW]]);

        $html = $this->actingAs($rider)->get(route('dashboard'))->assertOk()->getContent();
        $nav = $this->nav($html);
        $this->assertStringContainsString('Fiche de JACK', $nav);
        $this->assertStringContainsString('data-nav="sessions.index"', $nav);
        foreach (['budget.index', 'health.index', 'horses.documents.index', 'horses.sharing'] as $hidden) {
            $this->assertStringNotContainsString('data-nav="'.$hidden.'"', $nav, $hidden);
        }
        $this->assertStringContainsString('data-nav="sharing.index"', $nav);
        // Barre du bas : sans accès santé, l'agenda prend sa place.
        $bar = Str::betweenFirst($html, 'aria-label="Navigation rapide"', '</nav>');
        $this->assertStringContainsString('data-bar="calendar.index"', $bar);
        $this->assertStringNotContainsString('data-bar="health.index"', $bar);
    }
}
