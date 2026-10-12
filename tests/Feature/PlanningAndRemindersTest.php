<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\CareReminderRule;
use App\Models\Exercise;
use App\Models\HorseAccessGrant;
use App\Models\RidingSession;
use App\Services\CareReminders;
use App\Support\Perm;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningAndRemindersTest extends TestCase
{
    private function newSession($user, $horse, array $extra = []): RidingSession
    {
        $this->actingAs($user)->post('/seances', $extra + ['horse_id' => $horse->id, 'scheduled_at' => now()->addDay()->format('Y-m-d 18:00'), 'session_type' => 'dressage', 'objective' => 'Transitions', 'planned_minutes' => 45])->assertRedirect();

        return RidingSession::latest('id')->firstOrFail();
    }

    // ---------------------------------------------------------------- Glisser-déposer

    public function test_drag_and_drop_layout_reorders_moves_and_inserts_from_library(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $session = $this->newSession($user, $horse);
        $ex = Exercise::where('scope', 'default')->take(3)->get();
        $this->actingAs($user)->post("/seances/{$session->id}/exercices", ['items' => [['exercise_id' => $ex[0]->id, 'phase' => 'main'], ['exercise_id' => $ex[1]->id, 'phase' => 'main']]]);
        [$a, $b] = $session->items()->orderBy('position')->get()->all();

        $this->actingAs($user)->get("/seances/{$session->id}/preparer")->assertOk()->assertSee('data-phase="warmup"', false)->assertSee('data-token="ex:'.$ex[2]->id.'"', false);

        $this->actingAs($user)->postJson("/seances/{$session->id}/exercices/ordre", ['layout' => [
            'warmup' => ['item:'.$b->id, 'ex:'.$ex[2]->id],
            'main' => ['item:'.$a->id],
            'cooldown' => [],
        ]])->assertOk()->assertJson(['added' => 1]);

        $items = $session->items()->orderBy('position')->get();
        $this->assertSame([$b->id, $items[1]->id, $a->id], $items->pluck('id')->all());
        $this->assertSame(['warmup', 'warmup', 'main'], $items->pluck('phase')->all());
        $this->assertSame($ex[2]->name, $items[1]->name); // copie de l'exercice de la bibliothèque
    }

    public function test_layout_rejects_unknown_tokens_and_foreign_items(): void
    {
        $user = $this->user();
        $session = $this->newSession($user, $this->horseFor($user));
        $this->actingAs($user)->postJson("/seances/{$session->id}/exercices/ordre", ['layout' => ['main' => ['drop table']]])->assertStatus(422);
        $this->actingAs($user)->postJson("/seances/{$session->id}/exercices/ordre", ['layout' => ['nowhere' => []]])->assertStatus(422);

        $other = $this->user();
        $otherSession = $this->newSession($other, $this->horseFor($other));
        $this->actingAs($other)->post("/seances/{$otherSession->id}/exercices", ['items' => [['name' => 'Libre']]]);
        $foreign = $otherSession->items()->first();
        $this->actingAs($user)->postJson("/seances/{$session->id}/exercices/ordre", ['layout' => ['main' => ['item:'.$foreign->id]]])->assertOk();
        $this->assertSame($otherSession->id, $foreign->fresh()->riding_session_id);
        $this->actingAs($user)->postJson("/seances/{$otherSession->id}/exercices/ordre", ['layout' => ['main' => []]])->assertForbidden();
    }

    public function test_layout_is_refused_once_session_is_completed(): void
    {
        $user = $this->user();
        $session = $this->newSession($user, $this->horseFor($user));
        $this->actingAs($user)->put("/seances/{$session->id}/bilan", ['actual_minutes' => 40]);
        $this->actingAs($user)->postJson("/seances/{$session->id}/exercices/ordre", ['layout' => ['main' => []]])->assertStatus(422);
    }

    // ---------------------------------------------------------------- Séances dans le calendrier

    public function test_sessions_appear_in_calendar_and_follow_completion(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $session = $this->newSession($user, $horse);
        $event = CalendarEvent::where('source_id', $session->id)->where('type', 'session')->firstOrFail();
        $this->assertSame('planned', $event->status);

        $this->actingAs($user)->put("/seances/{$session->id}/bilan", ['actual_minutes' => 50, 'progress' => 'OK'])->assertRedirect();
        $event->refresh();
        $this->assertSame('done', $event->status);
        $this->assertSame(50, $event->duration_minutes);

        $month = $session->scheduled_at->toDateString();
        $this->actingAs($user)->get('/calendrier?date='.$month)->assertOk()->assertSee($event->title);
        $this->actingAs($user)->get("/calendrier/{$event->id}")->assertRedirect("/seances/{$session->id}");
        // L'événement suit la séance : pas de modification directe
        $this->actingAs($user)->delete("/calendrier/{$event->id}")->assertStatus(422);

        $session->delete();
        $this->assertNull(CalendarEvent::find($event->id));
    }

    public function test_session_events_hidden_without_session_permission(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $session = $this->newSession($owner, $horse);
        $title = CalendarEvent::where('source_id', $session->id)->value('title');
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => [Perm::HORSE_VIEW, Perm::CALENDAR_VIEW]]);
        $this->actingAs($viewer)->get('/calendrier?date='.$session->scheduled_at->toDateString())->assertOk()->assertDontSee($title);
    }

    // ---------------------------------------------------------------- Fréquence et rappels

    public function test_farrier_and_vaccination_reminders_are_enabled_by_default(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $rules = app(CareReminders::class)->rulesFor($horse)->keyBy(fn ($r) => $r['category']->key);
        $this->assertTrue($rules['farrier']['enabled']);
        $this->assertSame([6, 'weeks'], [$rules['farrier']['interval_value'], $rules['farrier']['interval_unit']]);
        $this->assertTrue($rules['vaccination']['enabled']);
        $this->assertFalse($rules['deworming']['enabled']);
        $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertSee('Rappels de suivi')->assertSee('Tous les 6 semaines');
    }

    public function test_validating_care_asks_next_frequency_and_remembers_it(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $farrier = CareCategory::where('key', 'farrier')->first();
        $this->actingAs($user)->get("/chevaux/{$horse->id}/soins/nouveau")->assertOk()->assertSee('Prochain passage')->assertSee('Retenir cette fréquence');

        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", [
            'care_category_id' => $farrier->id, 'performed_at' => '2026-10-01 10:00',
            'next_mode' => 'interval', 'next_interval_value' => 8, 'next_interval_unit' => 'weeks', 'remember_frequency' => '1',
        ])->assertRedirect();
        $care = CareRecord::latest('id')->first();
        $this->assertSame('2026-11-26', $care->next_check_on->toDateString());
        $this->assertDatabaseHas('care_reminder_rules', ['horse_id' => $horse->id, 'care_category_id' => $farrier->id, 'interval_value' => 8, 'interval_unit' => 'weeks', 'enabled' => true]);
        $this->assertDatabaseHas('calendar_events', ['source_id' => $care->id, 'type' => 'farrier', 'status' => 'planned']);

        // Passage suivant : l'ancien contrôle est clos, plus de rappel pour lui
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", [
            'care_category_id' => $farrier->id, 'performed_at' => '2026-11-20 10:00', 'next_mode' => 'interval', 'next_interval_value' => 2, 'next_interval_unit' => 'months',
        ])->assertRedirect();
        $this->assertNotNull($care->fresh()->next_check_done_at);
        $this->assertSame('done', CalendarEvent::where('source_id', $care->id)->value('status'));
        $this->assertSame('2027-01-20', CareRecord::latest('id')->first()->next_check_on->toDateString());

        // « Pas de suivi »
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => CareCategory::where('key', 'injury')->value('id'), 'performed_at' => '2026-11-21 10:00', 'next_mode' => 'none', 'next_check_on' => '2027-01-01'])->assertRedirect();
        $this->assertNull(CareRecord::latest('id')->first()->next_check_on);
    }

    public function test_validate_planned_intervention_from_calendar(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        // Rendez-vous pris à l'avance (un rendez-vous saisi après coup crée directement le soin : voir CareCalendarSyncTest).
        $event = new CalendarEvent(['horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Maréchal', 'starts_at' => now()->addHour()]);
        $event->organization_id = $horse->organization_id;
        $event->save();
        $this->actingAs($user)->get("/calendrier/{$event->id}")->assertOk()->assertSee('Valider l\'intervention', false);
        $this->actingAs($user)->get("/chevaux/{$horse->id}/soins/nouveau?evenement={$event->id}")->assertOk()->assertSee('Validation de l\'intervention', false);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", [
            'care_category_id' => CareCategory::where('key', 'farrier')->value('id'), 'performed_at' => now()->format('Y-m-d H:i'),
            'calendar_event_id' => $event->id, 'next_mode' => 'interval', 'next_interval_value' => 6, 'next_interval_unit' => 'weeks',
        ])->assertRedirect();
        $this->assertSame('done', $event->fresh()->status);
        // Le rendez-vous validé devient l'événement du soin : pas de doublon dans l'agenda.
        $care = CareRecord::where('horse_id', $horse->id)->latest('id')->first();
        $this->assertSame($care->id, $event->fresh()->care_record_id);
        $this->assertSame(1, CalendarEvent::where('care_record_id', $care->id)->count());
    }

    public function test_reminder_settings_and_sending(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $vacc = CareCategory::where('key', 'vaccination')->first();
        $farrier = CareCategory::where('key', 'farrier')->first();

        $this->actingAs($user)->put("/chevaux/{$horse->id}/rappels-suivi", ['rules' => [
            $vacc->id => ['enabled' => '0', 'interval_value' => 6, 'interval_unit' => 'months', 'notice_days' => 10],
            $farrier->id => ['enabled' => '1', 'interval_value' => 7, 'interval_unit' => 'weeks', 'notice_days' => 5],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(CareReminderRule::where('care_category_id', $vacc->id)->first()->enabled);

        $mk = fn ($cat, $due) => CareRecord::forceCreate(['uuid' => (string) Str::uuid(), 'horse_id' => $horse->id, 'care_category_id' => $cat->id, 'performed_at' => now()->subMonth(), 'next_check_on' => $due]);
        $mk($farrier, now()->addDays(5)->toDateString());   // préavis de 5 jours
        $mk($vacc, now()->addDays(10)->toDateString());     // rappel désactivé
        Artisan::call('reminders:send');
        $notes = $user->notifications()->get();
        $this->assertCount(1, $notes);
        $this->assertStringContainsString('Maréchal', $notes->first()->data['body'] ?? json_encode($notes->first()->data));

        // Idempotent
        Artisan::call('reminders:send');
        $this->assertCount(1, $user->notifications()->get());

        // Rappel le jour même
        $this->travel(5)->days();
        Artisan::call('reminders:send');
        $this->assertCount(2, $user->notifications()->get());
    }

    public function test_reminder_settings_require_health_edit(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => Perm::halfLeasePreset()]);
        $farrier = CareCategory::where('key', 'farrier')->first();
        $this->actingAs($rider)->put("/chevaux/{$horse->id}/rappels-suivi", ['rules' => [$farrier->id => ['enabled' => '0', 'interval_value' => 6, 'interval_unit' => 'weeks', 'notice_days' => 7]]])->assertForbidden();
    }
}
