<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use Tests\TestCase;

/** Synchronisation de l'agenda avec Google Agenda / Calendrier Apple (abonnement iCalendar), réservée aux offres payantes. */
class CalendarFeedTest extends TestCase
{
    private function event($user, $horse, array $attrs = []): CalendarEvent
    {
        $e = new CalendarEvent($attrs + ['horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Maréchal', 'starts_at' => now()->addDays(2)->setTime(9, 30), 'duration_minutes' => 45, 'status' => 'confirmed']);
        $e->organization_id = $horse->organization_id;
        $e->created_by = $user->id;
        $e->save();

        return $e;
    }

    public function test_paid_user_subscribes_and_sees_only_their_events(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user, null, ['official_name' => 'UNIVERS DES TILLEULS', 'usual_name' => 'Uni']);
        $this->event($user, $horse, ['notes' => 'Ferrure complète, prévoir 2 h ; apporter le passeport, merci']);
        $this->event($user, $horse, ['type' => 'session', 'title' => 'Longe', 'recurrence' => 'weekly', 'recurrence_interval' => 2, 'starts_at' => now()->subWeek()->setTime(18, 0)]);
        $this->event($user, $horse, ['type' => 'veterinary', 'title' => 'Vaccin', 'all_day' => true, 'status' => 'cancelled']);
        $other = $this->user();
        $this->grantLicense($other->personalOrganization(), 'particulier');
        $this->event($other, $this->horseFor($other), ['title' => 'Rendez-vous secret']);

        $page = $this->actingAs($user)->get('/calendrier')->assertOk()->assertSee('data-calendar-sync', false)->assertDontSee('data-upsell="calendar_sync"', false);
        $url = $user->fresh()->calendar_token ? route('calendar.feed', $user->fresh()->calendar_token) : null;
        $this->assertNotNull($url);
        $page->assertSee('webcal://', false)->assertSee('calendar.google.com/calendar/r?cid=', false);

        $ics = $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:Maréchal – Uni', $ics);
        $this->assertStringContainsString('DTSTART:'.now()->addDays(2)->setTime(9, 30)->utc()->format('Ymd\THis\Z'), $ics);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;INTERVAL=2', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:', $ics);
        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
        $this->assertStringContainsString('prévoir 2 h \; apporter le passeport\\, merci', str_replace("\r\n ", '', $ics));
        $this->assertStringNotContainsString('Rendez-vous secret', $ics);
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'Lignes pliées à 75 octets.');
        }

        // Nouvelle adresse : l'ancienne ne fonctionne plus.
        $this->actingAs($user)->post('/calendrier/synchronisation/nouvelle-adresse')->assertSessionHas('success');
        $this->get($url)->assertNotFound();
        $this->get(route('calendar.feed', $user->fresh()->calendar_token))->assertOk();
        $this->get('/agenda/abonnement/'.str_repeat('a', 48).'.ics')->assertNotFound();
    }

    public function test_free_user_sees_an_offer_and_feed_pauses_when_subscription_ends(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->event($user, $horse);
        $this->actingAs($user)->get('/calendrier')->assertOk()->assertSee('data-upsell="calendar_sync"', false)
            ->assertSee('Votre agenda Jack&amp;Cie dans votre téléphone', false)->assertDontSee('data-calendar-sync', false);
        $this->actingAs($user)->post('/calendrier/synchronisation/nouvelle-adresse')->assertForbidden();

        // Abonné dont l'offre prend fin : l'agenda externe se vide, avec une explication.
        $user->forceFill(['calendar_token' => str_repeat('b', 48)])->save();
        $ics = $this->get(route('calendar.feed', str_repeat('b', 48)))->assertOk()->getContent();
        $this->assertStringContainsString('Synchronisation Jack&Cie en pause', $ics);
        $this->assertStringNotContainsString('SUMMARY:Maréchal', $ics);
    }
}
