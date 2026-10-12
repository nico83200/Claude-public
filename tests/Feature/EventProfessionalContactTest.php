<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Professional;
use Tests\TestCase;

/** Événement : intervenant cliquable, boutons Appeler et SMS (affichés sur téléphone uniquement). */
class EventProfessionalContactTest extends TestCase
{
    public function test_event_shows_professional_link_call_and_sms(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user, null, ['usual_name' => 'Uni']);
        $pro = Professional::forceCreate(['organization_id' => $horse->organization_id, 'first_name' => 'Julien', 'last_name' => 'Moreau', 'kind' => 'farrier', 'phone' => '06 12 34 56 78']);
        $event = CalendarEvent::forceCreate(['horse_id' => $horse->id, 'organization_id' => $horse->organization_id, 'type' => 'farrier', 'title' => 'Maréchal',
            'starts_at' => '2026-10-20 09:30:00', 'duration_minutes' => 60, 'status' => 'planned', 'created_by' => $user->id, 'professional_id' => $pro->id]);

        $page = $this->actingAs($user)->get('/calendrier/'.$event->id)->assertOk()
            ->assertSee('data-professional-contact', false)
            ->assertSee('href="'.route('professionals.show', $pro).'"', false)
            ->assertSee('href="tel:0612345678"', false)
            ->assertSee('x-show="$store.install.phone"', false);
        $this->assertMatchesRegularExpression('/href="sms:0612345678\?&amp;body=Bonjour[^"]*mardi%2020%20octobre[^"]*Uni/', $page->getContent());

        $this->actingAs($user)->get('/intervenants/'.$pro->id)->assertOk()->assertSee('href="tel:0612345678"', false)->assertSee('href="sms:0612345678"', false);

        // Sans numéro : pas de boutons ; intervenant d'un autre espace : nom sans lien.
        $pro->update(['phone' => null]);
        $this->actingAs($user)->get('/calendrier/'.$event->id)->assertDontSee('data-phone-actions', false);
    }
}
