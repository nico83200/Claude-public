<?php

namespace Tests\Feature;

use App\Services\HorseAccess;
use Tests\TestCase;

/** Plusieurs chevaux : puces « Tous · cheval… » en haut des pages, qui gardent la page en cours. */
class Version239RequestsTest extends TestCase
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

    public function test_chips_on_list_and_horse_pages_with_current_horse(): void
    {
        [$user, $jack, $uni] = $this->owner();
        foreach (['budget.index', 'sessions.index', 'calendar.index', 'health.index'] as $route) {
            $this->fresh($user);
            $this->actingAs($user)->get(route($route))->assertOk()->assertSee('data-horse-switch', false)
                ->assertSee('data-horse-switch-chip="'.$uni->id.'"', false);
        }
        $this->fresh($user);
        $html = $this->actingAs($user)->get(route('budget.index', ['horse' => $uni->id]))->getContent();
        $this->assertMatchesRegularExpression('#aria-current="true"[^>]*data-horse-switch-chip="'.$uni->id.'"#', $html);
        $this->fresh($user);
        $html = $this->actingAs($user)->get(route('horses.care.index', $jack))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#aria-current="true"[^>]*data-horse-switch-chip="'.$jack->id.'"#', $html);
        // Santé › Poids : le choix propre à l'onglet (cheval requis), pas les puces communes.
        $this->fresh($user);
        $this->actingAs($user)->get(route('health.index', ['onglet' => 'poids']))->assertOk()->assertSee('data-health-horses', false)->assertDontSee('data-horse-switch', false);
        // Pas de puces ailleurs (paramètres) ni avec un seul cheval.
        $this->fresh($user);
        $this->actingAs($user)->get(route('settings.profile'))->assertDontSee('data-horse-switch', false);
        $solo = $this->user();
        $this->horseFor($solo);
        $this->actingAs($solo)->get(route('budget.index'))->assertDontSee('data-horse-switch', false);
    }

    public function test_choosing_a_chip_reopens_the_same_page_for_that_horse(): void
    {
        [$user, $jack, $uni] = $this->owner();
        // Liste : ?horse= remplacé, autres filtres gardés.
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $uni->id, 'back' => 'budget.index', 'query' => ['year' => '2026', 'horse' => $jack->id]])
            ->assertRedirect(route('budget.index', ['year' => '2026', 'horse' => $uni->id]));
        $this->assertSame($uni->id, $user->profile()->value('active_horse_id'));
        $this->actingAs($user)->post(route('focus.set'), ['horse' => 'all', 'back' => 'calendar.index', 'query' => ['view' => 'week']])
            ->assertRedirect(route('calendar.index', ['view' => 'week']));
        // Santé : onglet gardé.
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $jack->id, 'back' => 'health.index', 'query' => ['onglet' => 'poids']])
            ->assertRedirect(route('health.index', ['onglet' => 'poids', 'cheval' => $jack->id]));
        // Page d'un cheval : même page pour l'autre ; « Tous » mène à la page commune.
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $uni->id, 'back' => 'horses.logs.index'])->assertRedirect(route('horses.logs.index', $uni));
        $this->actingAs($user)->post(route('focus.set'), ['horse' => 'all', 'back' => 'horses.logs.index'])->assertRedirect(route('health.index', ['onglet' => 'suivi']));
        // Page inconnue : accueil, comme avant ; cheval d'un autre refusé.
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $uni->id, 'back' => 'admin.dashboard'])->assertRedirect(route('dashboard'));
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $this->horseFor($this->user())->id, 'back' => 'budget.index'])->assertNotFound();
    }
}
