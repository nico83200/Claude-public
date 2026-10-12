<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Professional;
use Tests\TestCase;

/** Synchronisation Agenda ↔ Santé : ce qui est saisi d'un côté apparaît de l'autre. */
class CareCalendarSyncTest extends TestCase
{
    public function test_farrier_event_entered_in_agenda_appears_in_health_and_stays_in_sync(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user, null, ['usual_name' => 'Uni']);
        $pro = Professional::forceCreate(['organization_id' => $horse->organization_id, 'first_name' => 'Julien', 'last_name' => 'Moreau', 'kind' => 'farrier']);

        // Rendez-vous de maréchalerie saisi après coup dans l'agenda → soin dans Santé.
        $this->actingAs($user)->post('/calendrier', ['horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Ferrure complète', 'date' => now()->subDay()->toDateString(), 'time' => '10:00',
            'duration_minutes' => 60, 'professional_id' => $pro->id, 'notes' => 'Fers neufs'])->assertRedirect();
        $event = CalendarEvent::where('title', 'Ferrure complète')->firstOrFail();
        $care = CareRecord::where('horse_id', $horse->id)->firstOrFail();
        $this->assertSame([$care->id, 'done'], [$event->care_record_id, $event->status]);
        $this->assertSame('farrier', $care->category->key);
        $this->assertSame([$pro->id, 'Ferrure complète', 'Fers neufs'], [$care->professional_id, $care->reason, $care->observations]);
        $this->assertSame($event->starts_at->toDateTimeString(), $care->performed_at->toDateTimeString());
        $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertSee('Ferrure complète');

        // Modifier l'événement met le soin à jour ; supprimer l'événement retire le soin.
        $this->actingAs($user)->put("/calendrier/{$event->id}", ['horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Ferrure complète', 'date' => now()->subDays(2)->toDateString(), 'time' => '15:30', 'duration_minutes' => 60, 'status' => 'done'])->assertRedirect();
        $this->assertSame(now()->subDays(2)->toDateString().' 15:30', $care->fresh()->performed_at->format('Y-m-d H:i'));
        $this->actingAs($user)->delete("/calendrier/{$event->id}")->assertRedirect();
        $this->assertSoftDeleted($care);
    }

    public function test_upcoming_appointment_is_listed_in_health_and_becomes_a_care_when_done(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/calendrier', ['horse_id' => $horse->id, 'type' => 'veterinary', 'title' => 'Vaccins', 'date' => now()->addWeek()->toDateString(), 'time' => '09:00', 'duration_minutes' => 30])->assertRedirect();
        $event = CalendarEvent::where('title', 'Vaccins')->firstOrFail();
        $this->assertNull($event->care_record_id);
        $this->assertSame(0, CareRecord::where('horse_id', $horse->id)->count(), 'Un rendez-vous à venir n\'est pas un soin réalisé.');
        $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertSee('data-care-appointments', false)->assertSee('Vaccins');

        // Marqué « Réalisé » dans l'agenda → soin créé.
        $this->actingAs($user)->put("/calendrier/{$event->id}", ['horse_id' => $horse->id, 'type' => 'veterinary', 'title' => 'Vaccins', 'date' => now()->toDateString(), 'time' => '09:00', 'duration_minutes' => 30, 'status' => 'done'])->assertRedirect();
        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $this->assertSame('veterinary', $care->category->key);
        $this->assertSame($care->id, $event->fresh()->care_record_id);

        // Les autres types d'événements ne créent pas de soin.
        $this->actingAs($user)->post('/calendrier', ['horse_id' => $horse->id, 'type' => 'ride', 'title' => 'Balade', 'date' => now()->subDay()->toDateString(), 'time' => '10:00', 'duration_minutes' => 60]);
        $this->assertSame(1, CareRecord::where('horse_id', $horse->id)->count());
    }

    public function test_care_entered_in_health_appears_in_agenda(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $farrier = CareCategory::where('key', 'farrier')->value('id');
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => $farrier, 'performed_at' => now()->subDays(3)->format('Y-m-d').' 11:00', 'reason' => 'Parage'])->assertRedirect();
        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $event = CalendarEvent::where('care_record_id', $care->id)->sole();
        $this->assertSame(['farrier', 'done'], [$event->type, $event->status]);
        $this->assertSame($care->performed_at->toDateTimeString(), $event->starts_at->toDateTimeString());
        $this->actingAs($user)->get('/calendrier?view=month&date='.$care->performed_at->toDateString())->assertSee('Maréchal');

        // Modifier le soin déplace l'événement ; supprimer le soin retire l'événement.
        $this->actingAs($user)->put("/chevaux/{$horse->id}/soins/{$care->id}", ['care_category_id' => $farrier, 'performed_at' => now()->subDays(5)->format('Y-m-d').' 08:30', 'reason' => 'Parage', 'version' => $care->version])->assertRedirect();
        $this->assertSame(now()->subDays(5)->format('Y-m-d').' 08:30', $event->fresh()->starts_at->format('Y-m-d H:i'));
        $this->actingAs($user)->delete("/chevaux/{$horse->id}/soins/{$care->id}")->assertRedirect();
        $this->assertSoftDeleted($event);
    }

    public function test_clicking_an_event_opens_details_and_edit_is_a_separate_page(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/calendrier', ['horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Maréchal', 'date' => now()->addDays(3)->toDateString(), 'time' => '09:00', 'duration_minutes' => 60]);
        $event = CalendarEvent::where('title', 'Maréchal')->firstOrFail();
        $this->actingAs($user)->get("/calendrier/{$event->id}")->assertOk()->assertSee('data-event-details', false)
            ->assertSee("/calendrier/{$event->id}/modifier", false)->assertDontSee('name="title"', false);
        $this->actingAs($user)->get("/calendrier/{$event->id}/modifier")->assertOk()->assertSee('name="title"', false)->assertSee('name="status"', false);
        $this->actingAs($this->user())->get("/calendrier/{$event->id}/modifier")->assertForbidden();
    }
}
