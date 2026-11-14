<?php

namespace Tests\Feature;

use App\Http\Middleware\ReturnToOrigin;
use App\Models\DailyLog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Retour au même endroit : après une modification ou une suppression, retour à la liste d'origine (sur la ligne, côté navigateur). */
class Version254RequestsTest extends TestCase
{
    private function setUpLog(): array
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $log = DailyLog::create(['horse_id' => $horse->id, 'author_id' => $owner->id, 'logged_at' => now(), 'appetite' => 'good', 'general_state' => 'good']);

        return [$owner, $horse, $log];
    }

    public function test_update_and_delete_return_to_the_page_of_origin(): void
    {
        [$owner, $horse, $log] = $this->setUpLog();
        $payload = ['logged_at' => now()->format('Y-m-d H:i'), 'appetite' => 'reduced', 'general_state' => 'good'];

        // Modifiée depuis sa page, ouverte depuis la page Santé : retour à la page Santé.
        $this->actingAs($owner)->put(route('horses.logs.update', [$horse, $log]), $payload + ['_return' => '/sante?onglet=suivi'])
            ->assertRedirect(url('/sante?onglet=suivi'))->assertSessionHas('success', 'Saisie modifiée.');
        $this->assertSame('reduced', $log->fresh()->appetite);
        // Sans origine : comme avant.
        $this->actingAs($owner)->put(route('horses.logs.update', [$horse, $log]), $payload)->assertRedirect(route('horses.logs.index', $horse));
        // Saisie refusée : on reste sur le formulaire, rien n'est modifié.
        $this->actingAs($owner)->from(route('horses.logs.edit', [$horse, $log]))
            ->put(route('horses.logs.update', [$horse, $log]), ['appetite' => 'nimporte', '_return' => '/sante'])->assertRedirect(route('horses.logs.edit', [$horse, $log]));
        // Suppression : retour à la liste d'origine aussi.
        $this->actingAs($owner)->delete(route('horses.logs.destroy', [$horse, $log]), ['_return' => '/dashboard'])->assertRedirect(url('/dashboard'));
        $this->assertSame(0, DailyLog::count());
    }

    public function test_only_internal_addresses_and_never_for_a_creation_or_a_conflict(): void
    {
        [$owner, $horse, $log] = $this->setUpLog();
        $payload = ['logged_at' => now()->format('Y-m-d H:i'), 'appetite' => 'good', 'general_state' => 'good'];
        foreach (['//ailleurs.fr/piege', 'https://ailleurs.fr', '/\\ailleurs.fr', 'budget', "/bud\nget"] as $evil) {
            $this->actingAs($owner)->put(route('horses.logs.update', [$horse, $log]), $payload + ['_return' => $evil])->assertRedirect(route('horses.logs.index', $horse));
        }
        $this->assertTrue(ReturnToOrigin::isInternal('/budget?horse=3'));

        // Création : le comportement reste celui de la page (pas de retour forcé).
        $this->actingAs($owner)->from(route('horses.logs.index', $horse))->post(route('horses.logs.store', $horse), $payload + ['_return' => '/dashboard'])
            ->assertRedirect(route('horses.logs.index', $horse));

        // Conflit ou erreur signalée par la page : on reste où l'on est.
        Route::middleware('web')->put('/_test-retour', fn () => back()->with('error', 'Modifié par quelqu\'un d\'autre.'));
        $this->actingAs($owner)->from('/formulaire')->put('/_test-retour', ['_return' => '/budget'])->assertRedirect(url('/formulaire'));
    }

    public function test_edit_forms_are_marked_to_return_to_their_list(): void
    {
        [$owner, $horse, $log] = $this->setUpLog();
        $this->actingAs($owner)->get(route('horses.logs.edit', [$horse, $log]))->assertOk()->assertSee('data-return-origin', false);
        $this->actingAs($owner)->get(route('horses.care.create', $horse))->assertOk()->assertSee('data-return-origin', false);
    }
}
