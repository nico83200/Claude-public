<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\HorseAccessGrant;
use App\Models\RidingSession;
use Tests\TestCase;

/** Liste des séances : glisser une ligne pour la supprimer, avec « Annuler ». */
class SessionSwipeDeleteTest extends TestCase
{
    public function test_session_swiped_away_can_be_restored_with_its_calendar_event(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->addDay()->format('Y-m-d').' 10:00', 'session_type' => 'dressage'])->assertRedirect();
        $session = RidingSession::where('horse_id', $horse->id)->sole();
        $morph = $session->getMorphClass();
        $this->assertSame(1, CalendarEvent::where('source_type', $morph)->where('source_id', $session->id)->count());

        // La ligne peut être glissée, l'astuce est présente (affichée sur écran tactile seulement).
        $this->actingAs($owner)->get('/seances')->assertOk()->assertSee('data-swipe-row', false)->assertSee('data-swipe-hint', false)
            ->assertSee('glissez une séance vers la gauche pour la supprimer', false);

        // Suppression par le geste : réponse JSON avec l'adresse d'annulation ; l'événement d'agenda disparaît.
        $restoreUrl = $this->actingAs($owner)->deleteJson("/seances/{$session->id}")->assertOk()->assertJson(['deleted' => true])->json('restore_url');
        $this->assertSoftDeleted($session);
        $this->assertSame(0, CalendarEvent::where('source_type', $morph)->where('source_id', $session->id)->count());
        $this->actingAs($owner)->get('/seances')->assertDontSee('data-swipe-row', false);

        // « Annuler » : séance et événement reviennent.
        $this->actingAs($owner)->postJson($restoreUrl)->assertOk()->assertJson(['restored' => true]);
        $this->assertNotSoftDeleted($session);
        $this->assertSame(1, CalendarEvent::where('source_type', $morph)->where('source_id', $session->id)->count());
        // Une séance non supprimée ne se « restaure » pas.
        $this->actingAs($owner)->postJson($restoreUrl)->assertNotFound();

        // La suppression classique (formulaire, ordinateur) redirige toujours vers la liste.
        $this->actingAs($owner)->delete("/seances/{$session->id}")->assertRedirect();
    }

    public function test_only_people_allowed_to_edit_get_the_swipe_and_can_restore(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->addDay()->format('Y-m-d').' 10:00', 'session_type' => 'dressage']);
        $session = RidingSession::where('horse_id', $horse->id)->sole();

        // Personne qui voit les séances sans pouvoir les modifier : pas de glissé, ni suppression, ni restauration.
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'sessions.view']]);
        $this->actingAs($viewer)->get('/seances')->assertOk()->assertDontSee('data-swipe-row', false)->assertDontSee('data-swipe-hint', false);
        $this->actingAs($viewer)->deleteJson("/seances/{$session->id}")->assertForbidden();

        $this->actingAs($owner)->deleteJson("/seances/{$session->id}")->assertOk();
        $this->actingAs($viewer)->postJson("/seances/{$session->id}/restaurer")->assertForbidden();
        $this->actingAs($this->user())->postJson("/seances/{$session->id}/restaurer")->assertForbidden();
        $this->assertSoftDeleted($session);
    }
}
