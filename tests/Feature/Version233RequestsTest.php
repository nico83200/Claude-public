<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\RidingSession;
use App\Support\TimeStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Statut des séances et rendez-vous selon l'heure : prévu, en cours, à valider. */
class Version233RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_status_follows_start_and_end(): void
    {
        $start = Carbon::parse('2026-10-17 10:00');
        $end = $start->copy()->addMinutes(45);
        $at = fn ($time, $stored = 'planned', $fem = false) => TimeStatus::resolve($stored, $start, $end, $fem, Carbon::parse('2026-10-17 '.$time));

        $this->assertSame(['planned', 'Prévu'], [$at('09:59')->key, $at('09:59')->label]);
        $this->assertSame('Confirmé', $at('09:00', 'confirmed')->label);
        $this->assertSame(['ongoing', 'En cours'], [$at('10:00')->key, $at('10:44')->label]);
        $this->assertSame(['to_validate', 'À valider'], [$at('10:45')->key, $at('10:45')->label]);
        $this->assertSame('Réalisé', $at('09:00', 'done')->label);
        $this->assertSame('Annulée', $at('10:10', 'cancelled', true)->label);
        $this->assertSame('Terminée', $at('11:00', 'completed', true)->label);
        // Séance démarrée en mode séance : encore « en cours » une heure après la fin prévue.
        $this->assertSame('ongoing', $at('11:30', 'in_progress', true)->key);
        $this->assertSame('to_validate', $at('11:45', 'in_progress', true)->key);
        // Données pour la mise à jour sur la page : seulement tant que le statut peut changer.
        $this->assertNotNull($at('10:10')->clientData());
        $this->assertNull($at('10:50')->clientData());
    }

    public function test_sessions_and_events_show_the_computed_status(): void
    {
        Carbon::setTestNow('2026-10-17 10:20');
        $user = $this->user();
        $horse = $this->horseFor($user);
        $mk = fn ($at, $minutes) => RidingSession::forceCreate(['horse_id' => $horse->id, 'created_by' => $user->id, 'rider_id' => $user->id,
            'scheduled_at' => Carbon::parse($at), 'planned_minutes' => $minutes, 'session_type' => 'free', 'status' => 'planned']);
        $soon = $mk('2026-10-17 18:00', 45);
        $now = $mk('2026-10-17 10:00', 45);
        $past = $mk('2026-10-17 08:00', 60);

        $html = $this->actingAs($user)->get(route('sessions.index'))->assertOk()->getContent();
        $badge = fn ($s) => Str::betweenFirst(Str::after($html, route('sessions.show', $s).'"'), 'data-status="', '"');
        $this->assertSame('planned', $badge($soon));
        $this->assertSame('ongoing', $badge($now));
        $this->assertSame('to_validate', $badge($past));
        $this->assertStringContainsString('data-time-status=', $html);

        // Séance passée : « Faire le bilan » en action principale.
        $this->actingAs($user)->get(route('sessions.show', $past))->assertOk()->assertSee('À valider')
            ->assertSee('class="btn-primary" data-debrief-link', false);

        $event = CalendarEvent::forceCreate(['uuid' => (string) Str::uuid(), 'organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'created_by' => $user->id,
            'type' => 'competition', 'title' => 'Concours', 'starts_at' => Carbon::parse('2026-10-17 10:00'), 'duration_minutes' => 30, 'status' => 'confirmed']);
        $this->actingAs($user)->get(route('calendar.show', $event))->assertOk()->assertSee('En cours');
        Carbon::setTestNow('2026-10-17 10:31');
        $this->actingAs($user)->get(route('calendar.show', $event))->assertOk()->assertSee('À valider');
        $this->assertSame('to_validate', $event->timeStatus()->key);
        $this->assertSame('confirmed', $event->timeStatus(Carbon::parse('2026-10-24 10:00'))->key, 'Occurrence d\'une série : à partir de sa date.');
    }
}
