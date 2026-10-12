<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\ExpenseCategory;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Models\TreatmentAdministration;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Tout ce qui est daté se retrouve dans l'agenda, et ce qui est saisi dans l'agenda arrive dans le bon module. */
class AgendaEverythingTest extends TestCase
{
    private function event(array $attrs): array
    {
        return $attrs + ['time' => '10:00', 'duration_minutes' => 45];
    }

    public function test_session_entered_in_agenda_becomes_a_real_session(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/calendrier', $this->event(['horse_id' => $horse->id, 'type' => 'lesson', 'title' => 'Cours avec Julie', 'date' => now()->addDays(2)->toDateString()]))
            ->assertSessionHas('success');
        $session = RidingSession::where('horse_id', $horse->id)->sole();
        $this->assertSame(['private_lesson', 'Cours avec Julie', 'planned'], [$session->session_type, $session->objective, $session->status]);
        $event = CalendarEvent::where('horse_id', $horse->id)->sole();
        $this->assertTrue($event->isSessionEvent());
        $this->actingAs($user)->get('/seances')->assertSee('Cours avec Julie');
    }

    public function test_treatment_is_synced_both_ways(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        // Agenda → Santé
        $this->actingAs($user)->post('/calendrier', $this->event(['horse_id' => $horse->id, 'type' => 'treatment', 'title' => 'Equipalazone', 'date' => now()->toDateString(), 'notes' => '1 sachet matin et soir']));
        $treatment = Treatment::where('horse_id', $horse->id)->sole();
        $this->assertSame(['Equipalazone', 'active', '1 sachet matin et soir'], [$treatment->product, $treatment->status, $treatment->instructions]);
        $event = CalendarEvent::where('treatment_id', $treatment->id)->sole();
        $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertSee('Equipalazone');

        $this->actingAs($user)->put("/calendrier/{$event->id}", $this->event(['horse_id' => $horse->id, 'type' => 'treatment', 'title' => 'Equipalazone 1 g', 'date' => now()->subDay()->toDateString(), 'status' => 'done']));
        $this->assertSame('Equipalazone 1 g', $treatment->fresh()->product);
        $this->assertSame(now()->subDay()->toDateString(), $treatment->fresh()->starts_on->toDateString());

        // Une fois des administrations enregistrées, la suppression depuis l'agenda est refusée (historique conservé).
        TreatmentAdministration::create(['treatment_id' => $treatment->id, 'administered_by' => $user->id, 'administered_at' => now()]);
        $this->actingAs($user)->delete("/calendrier/{$event->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($treatment);
        $this->actingAs($user)->get('/calendrier?view=day&date='.now()->toDateString())->assertSee('Donné : Equipalazone 1 g');

        // Santé → Agenda, avec la fin du traitement.
        $this->actingAs($user)->post("/chevaux/{$horse->id}/traitements", ['product' => 'Vermifuge', 'starts_on' => now()->addDays(3)->toDateString(), 'ends_on' => now()->addDays(5)->toDateString()])->assertSessionHasNoErrors();
        $t2 = Treatment::where('product', 'Vermifuge')->sole();
        $start = CalendarEvent::where('treatment_id', $t2->id)->sole();
        $this->assertSame([now()->addDays(3)->toDateString(), 'planned'], [$start->starts_at->toDateString(), $start->status]);
        $end = CalendarEvent::where('source_type', $t2->getMorphClass())->where('source_id', $t2->id)->sole();
        $this->assertStringContainsString('Fin de traitement', $end->title);

        // Supprimer dans l'agenda un traitement sans administration le retire de Santé.
        $this->actingAs($user)->delete("/calendrier/{$start->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($t2);
        $this->assertSoftDeleted($end);
    }

    public function test_planned_check_follows_the_care_and_can_be_validated_from_agenda(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $vaccine = CareCategory::where('key', 'vaccination')->value('id');
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => $vaccine, 'performed_at' => now()->subDays(2)->format('Y-m-d').' 10:00', 'reason' => 'Grippe', 'next_mode' => 'date', 'next_check_on' => now()->addMonths(6)->toDateString()]);
        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $check = CalendarEvent::where('source_type', $care->getMorphClass())->where('source_id', $care->id)->sole();
        $this->assertStringContainsString('Contrôle', $check->title);

        // Déplacer le contrôle dans l'agenda change la date dans le soin.
        $newDate = now()->addMonths(5)->toDateString();
        $this->actingAs($user)->put("/calendrier/{$check->id}", $this->event(['horse_id' => $horse->id, 'type' => 'veterinary', 'title' => $check->title, 'date' => $newDate, 'time' => '14:00', 'status' => 'confirmed']));
        $this->assertSame($newDate, $care->fresh()->next_check_on->toDateString());
        $care->fresh()->update(['reason' => 'Grippe-tétanos']);
        $this->assertSame('14:00', $check->fresh()->starts_at->format('H:i'), 'L\'heure choisie dans l\'agenda est conservée.');

        // Marqué réalisé : nouveau soin de la même catégorie, contrôle clos, sans doublon.
        $this->actingAs($user)->put("/calendrier/{$check->id}", $this->event(['horse_id' => $horse->id, 'type' => 'veterinary', 'title' => $check->title, 'date' => now()->toDateString(), 'time' => '09:00', 'status' => 'done']));
        $this->assertNotNull($care->fresh()->next_check_done_at);
        $new = CareRecord::where('horse_id', $horse->id)->whereKeyNot($care->id)->sole();
        $this->assertSame($vaccine, $new->care_category_id);
        $this->assertSame($new->id, $check->fresh()->care_record_id);
        $this->assertSame(1, CalendarEvent::where('care_record_id', $new->id)->count());
    }

    public function test_deleting_a_check_cancels_it_and_past_checks_create_no_phantom_care(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $farrier = CareCategory::where('key', 'farrier')->value('id');
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => $farrier, 'performed_at' => now()->subMonths(3)->format('Y-m-d').' 10:00', 'next_mode' => 'date', 'next_check_on' => now()->subMonth()->toDateString()]);
        $this->assertSame(1, CareRecord::where('horse_id', $horse->id)->count(), 'Un contrôle dans le passé n\'est pas un soin réalisé.');
        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $check = CalendarEvent::where('source_type', $care->getMorphClass())->sole();
        $this->actingAs($user)->delete("/calendrier/{$check->id}");
        $this->assertNull($care->fresh()->next_check_on);
        $this->assertNotSoftDeleted($care);
    }

    public function test_everything_dated_appears_in_agenda_for_allowed_users_only(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);
        $day = now()->toDateString();
        $this->actingAs($user)->post("/chevaux/{$horse->id}/poids", ['measured_on' => $day, 'method' => 'scale', 'weight_kg' => 512]);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/observations", ['body' => 'Légère boiterie AG', 'severity' => 'watch']);
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::value('id'), 'spent_on' => $day, 'amount' => '42', 'supplier' => 'Sellerie Dupont'])->assertSessionHasNoErrors();

        $page = $this->actingAs($user)->get('/calendrier?view=day&date='.$day)->assertOk();
        $page->assertSee('Poids : 512 kg')->assertSee('Légère boiterie AG')->assertSee('Sellerie Dupont')->assertSee('data-journal-item', false);
        $this->actingAs($user)->get('/calendrier?view=day&vue=rdv&date='.$day)->assertDontSee('Légère boiterie AG');
        $this->actingAs($user)->get('/calendrier?view=day&type=weight&date='.$day)->assertSee('Poids : 512 kg')->assertDontSee('Sellerie Dupont');
        $this->actingAs($this->user())->get('/calendrier?view=day&date='.$day)->assertDontSee('Légère boiterie AG')->assertDontSee('Poids : 512 kg');
    }

    public function test_weighing_reminder_shows_in_agenda_and_notifies_on_due_day(): void
    {
        Notification::fake();
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/poids", ['measured_on' => now()->subWeeks(4)->toDateString(), 'method' => 'scale', 'weight_kg' => 500]);
        $this->actingAs($user)->put("/chevaux/{$horse->id}/poids/rappel", ['weigh_every_weeks' => 4])->assertSessionHas('success');
        $this->assertSame(today()->toDateString(), $horse->fresh()->nextWeighingOn()->toDateString());
        $this->actingAs($user)->get("/chevaux/{$horse->id}/poids")->assertSee('data-weigh-reminder', false)->assertSee('aujourd');
        $this->actingAs($user)->get('/calendrier?view=day&date='.now()->toDateString())->assertSee('Pesée à faire');

        $this->artisan('reminders:send')->assertSuccessful();
        Notification::assertSentTo($user, AppNotification::class, fn ($n) => $n->kind === 'weight_due');
        $this->artisan('reminders:send');
        Notification::assertSentToTimes($user, AppNotification::class, 1);

        // Nouvelle pesée : le rappel passe 4 semaines plus tard.
        $this->actingAs($user)->post("/chevaux/{$horse->id}/poids", ['measured_on' => now()->toDateString(), 'method' => 'scale', 'weight_kg' => 505]);
        $this->assertSame(now()->addWeeks(4)->toDateString(), $horse->fresh()->nextWeighingOn()->toDateString());
        $this->actingAs($this->user())->put("/chevaux/{$horse->id}/poids/rappel", ['weigh_every_weeks' => 2])->assertForbidden();
    }
}
