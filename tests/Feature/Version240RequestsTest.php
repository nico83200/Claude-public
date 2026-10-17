<?php

namespace Tests\Feature;

use App\Http\Controllers\CareRecordController;
use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\HorseAccessGrant;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Services\CareCalendarSync;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Accueil : liste « À valider » (rendez-vous et séances passés), validation en un geste, pastille sur l'accueil. */
class Version240RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function event($horse, $user, array $extra = []): CalendarEvent
    {
        return CalendarEvent::forceCreate($extra + ['uuid' => (string) Str::uuid(), 'organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'created_by' => $user->id,
            'type' => 'competition', 'title' => 'Concours', 'starts_at' => now()->subDays(2)->setTime(10, 0), 'duration_minutes' => 60, 'status' => 'planned']);
    }

    private function ridingSession($horse, $user, array $extra = []): RidingSession
    {
        return RidingSession::forceCreate($extra + ['horse_id' => $horse->id, 'created_by' => $user->id, 'rider_id' => $user->id,
            'scheduled_at' => now()->subHours(3), 'planned_minutes' => 45, 'session_type' => 'free', 'status' => 'planned']);
    }

    private function ids(string $html, string $attr): array
    {
        preg_match_all('/'.$attr.'="(\d+)"/', $html, $m);

        return array_map('intval', array_values(array_unique($m[1])));
    }

    public function test_past_appointments_and_sessions_are_listed_on_the_home_page(): void
    {
        // Rendez-vous de soin pris à l'avance (saisi après coup, il serait déjà « réalisé » et au carnet).
        Carbon::setTestNow('2026-10-10 12:00');
        $user = $this->user();
        $horse = $this->horseFor($user);
        $farrier = $this->event($horse, $user, ['type' => 'farrier', 'title' => 'Maréchal', 'status' => 'confirmed', 'starts_at' => Carbon::parse('2026-10-16 09:00')]);
        Carbon::setTestNow('2026-10-17 12:00');
        $concours = $this->event($horse, $user);
        $this->event($horse, $user, ['title' => 'À venir', 'starts_at' => now()->addDay()]);
        $this->event($horse, $user, ['title' => 'En cours', 'starts_at' => now()->subMinutes(20)]); // fin dans 40 min
        $this->event($horse, $user, ['title' => 'Trop ancien', 'starts_at' => now()->subDays(40)]);
        $this->event($horse, $user, ['title' => 'Déjà fait', 'status' => 'done']);
        $this->event($horse, $user, ['title' => 'Annulé', 'status' => 'cancelled']);
        $this->event($horse, $user, ['type' => 'treatment', 'title' => 'Traitement']); // simple repère de traitement
        $session = $this->ridingSession($horse, $user);
        $this->ridingSession($horse, $user, ['scheduled_at' => now()->subMinutes(10)]); // en cours

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard-solo')
            ->assertSee('data-to-validate', false)->assertSee('Rejoint le carnet de santé une fois validé')->getContent();
        // Rendez-vous de soin en premier, puis la séance, puis le reste ; trois lignes sur l'accueil, « Voir tout » au-delà.
        $this->assertSame([$farrier->id, $concours->id], $this->ids($html, 'data-todo-event'));
        $this->assertSame([$session->id], $this->ids($html, 'data-todo-session'));
        $this->assertLessThan(strpos($html, 'data-todo-session="'.$session->id.'"'), strpos($html, 'data-todo-event="'.$farrier->id.'"'));
        $this->assertGreaterThan(strpos($html, 'data-todo-session="'.$session->id.'"'), strpos($html, 'data-todo-event="'.$concours->id.'"'));
        // Pastille sur l'accueil (barre du bas, menu).
        $this->assertStringContainsString('data-to-validate-bar>3<', $html);
        $this->assertStringContainsString('data-to-validate-count>3<', $html);
        $this->assertStringNotContainsString('data-to-validate-all', $html);
    }

    public function test_validate_cancel_and_debrief_from_the_list(): void
    {
        Carbon::setTestNow('2026-10-10 12:00');
        $user = $this->user();
        $horse = $this->horseFor($user);
        $farrier = $this->event($horse, $user, ['type' => 'farrier', 'title' => 'Maréchal', 'starts_at' => Carbon::parse('2026-10-16 09:00')]);
        $this->assertSame('planned', $farrier->fresh()->status);
        Carbon::setTestNow('2026-10-17 12:00');
        $concours = $this->event($horse, $user);
        $session = $this->ridingSession($horse, $user);

        // Valider le maréchal : réalisé, et le passage rejoint le carnet de santé ; on reste sur l'accueil.
        $this->actingAs($user)->from(route('dashboard'))->post(route('calendar.done', $farrier), ['stay' => 1])
            ->assertRedirect(route('dashboard'))->assertSessionHas('success', '« Maréchal » validé.');
        $this->assertSame('done', $farrier->fresh()->status);
        $this->assertNotNull($farrier->fresh()->care_record_id);
        $this->assertSame(CareCategory::where('key', 'farrier')->value('id'), CareRecord::find($farrier->fresh()->care_record_id)->care_category_id);

        // « Pas eu lieu » : annulé, gardé dans l'historique.
        $this->actingAs($user)->from(route('dashboard'))->post(route('calendar.cancel', $concours), ['stay' => 1])
            ->assertRedirect(route('dashboard'))->assertSessionHas('success', '« Concours » annulé.');
        $this->assertSame('cancelled', $concours->fresh()->status);
        $this->assertNotSoftDeleted($concours);
        $this->actingAs($user)->post(route('calendar.cancel', $concours))->assertStatus(422);

        // Séance : « Pas faite », ou le bilan.
        $this->actingAs($user)->get(route('dashboard'))->assertSee(route('sessions.debrief', $session), false);
        $this->actingAs($user)->from(route('dashboard'))->post(route('sessions.cancel', $session))->assertRedirect(route('dashboard'));
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertSame('cancelled', CalendarEvent::where('source_type', (new RidingSession)->getMorphClass())->where('source_id', $session->id)->value('status'));
        $this->actingAs($user)->post(route('sessions.cancel', $session))->assertStatus(422);

        // Plus rien à valider : ni bloc, ni pastille.
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('data-to-validate', false)->assertDontSee('data-to-validate-bar', false);

        // Bilan : la séance quitte aussi la liste.
        $other = $this->ridingSession($horse, $user, ['scheduled_at' => now()->subHours(5)]);
        $this->actingAs($user)->put(route('sessions.debrief.save', $other), ['rider_feeling' => 4])->assertRedirect();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('data-todo-session="'.$other->id.'"', false);
    }

    public function test_series_occurrences_of_the_last_week_and_planned_checks(): void
    {
        Carbon::setTestNow('2026-10-17 12:00'); // samedi
        $user = $this->user();
        $horse = $this->horseFor($user);
        // Paddock chaque jour à 8 h depuis un mois : seules les 7 dernières occurrences passées (10 → 17 octobre) sont proposées.
        $series = $this->event($horse, $user, ['title' => 'Paddock', 'recurrence' => 'daily', 'starts_at' => Carbon::parse('2026-09-15 08:00')]);
        $html = $this->actingAs($user)->get(route('todo.index'))->assertOk()->assertSee('À valider')->getContent();
        $this->assertSame(8, substr_count($html, 'data-todo-event="'.$series->id.'"'), '10 au 17 octobre, 8 h passées.');

        // Valider l'occurrence du 16 : on reste sur la liste ; elle devient un événement réalisé à part.
        $this->actingAs($user)->from(route('todo.index'))->post(route('calendar.done', [$series, 'date' => '2026-10-16']), ['stay' => 1])
            ->assertRedirect(route('todo.index'));
        $this->assertTrue($series->fresh()->isExcluded('2026-10-16'));
        $this->assertSame('done', CalendarEvent::where('recurrence_parent_id', $series->id)->value('status'));
        // « Pas eu lieu » pour le 15 : annulé à part.
        $this->actingAs($user)->post(route('calendar.cancel', [$series, 'date' => '2026-10-15']), ['stay' => 1]);
        $this->assertSame(['done', 'cancelled'], CalendarEvent::where('recurrence_parent_id', $series->id)->orderBy('starts_at', 'desc')->pluck('status')->all());
        $html = $this->actingAs($user)->get(route('todo.index'))->getContent();
        $this->assertSame(6, substr_count($html, 'data-todo-event="'.$series->id.'"'));

        // Contrôle prévu à la suite d'un soin (maréchal) : à valider une fois la date passée ; valider clôt le contrôle.
        $care = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => CareCategory::where('key', 'farrier')->value('id'), 'author_id' => $user->id,
            'performed_at' => now()->subWeeks(7), 'next_check_on' => now()->subDays(3)->toDateString()]);
        $check = app(CareCalendarSync::class)->planCheck($care);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('data-todo-event="'.$check->id.'"', false);
        $this->actingAs($user)->post(route('calendar.done', $check), ['stay' => 1]);
        $this->assertNotNull($care->fresh()->next_check_done_at);

        // Contrôle prévu qui n'a pas eu lieu : annulé (gardé dans l'historique), et de nouveau à prévoir.
        $vet = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => CareCategory::where('key', 'vaccination')->value('id'), 'author_id' => $user->id,
            'performed_at' => now()->subYear(), 'next_check_on' => now()->subDays(2)->toDateString()]);
        $missed = app(CareCalendarSync::class)->planCheck($vet);
        $this->actingAs($user)->from(route('dashboard'))->post(route('calendar.cancel', $missed), ['stay' => 1])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'contrôle à reprogrammer'));
        $this->assertSame(['cancelled', null], [$missed->fresh()->status, $missed->fresh()->source_id]);
        $this->assertNull($vet->fresh()->next_check_done_at);
        $this->assertNull(collect(app(CareRecordController::class)->nextChecks($horse))->firstWhere('care.id', $vet->id)['event']);
        $replanned = app(CareCalendarSync::class)->planCheck($vet->fresh(), now()->addDays(3)->setTime(10, 0));
        $this->assertNotSame($missed->id, $replanned->id);
        $this->assertSame('planned', $replanned->status);
    }

    public function test_only_what_the_person_may_validate(): void
    {
        Carbon::setTestNow('2026-10-17 12:00');
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $concours = $this->event($horse, $owner);
        $ownerSession = $this->ridingSession($horse, $owner);

        // Lecture seule : rien à valider, aucune pastille.
        $reader = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $reader->id, 'permissions' => [Perm::HORSE_VIEW, Perm::CALENDAR_VIEW, Perm::SESSIONS_VIEW]]);
        $this->actingAs($reader)->get(route('dashboard'))->assertOk()->assertDontSee('data-to-validate', false)->assertDontSee('data-to-validate-bar', false);
        $this->actingAs($reader)->post(route('calendar.cancel', $concours))->assertForbidden();

        // Demi-pension : ses propres séances seulement (pas celles du propriétaire), pas les rendez-vous sans droit d'agenda.
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW, Perm::SESSIONS_VIEW, Perm::SESSIONS_CREATE, Perm::SESSIONS_EDIT_OWN]]);
        $riderSession = $this->ridingSession($horse, $rider);
        app()->forgetInstance(HorseAccess::class);
        $html = $this->actingAs($rider)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertSame([$riderSession->id], $this->ids($html, 'data-todo-session'));
        $this->assertSame([], $this->ids($html, 'data-todo-event'));
        $this->actingAs($rider)->post(route('sessions.cancel', $ownerSession))->assertForbidden();
        $this->assertSame('planned', $ownerSession->fresh()->status);
    }

    public function test_all_horses_home_page_and_full_list_with_horse_filter(): void
    {
        Carbon::setTestNow('2026-10-17 12:00');
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        $jack = $this->horseFor($user, null, ['official_name' => 'JACKPOT', 'usual_name' => 'Jack']);
        $uni = $this->horseFor($user, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $this->allHorses($user);
        $events = collect(range(1, 4))->map(fn ($i) => $this->event($i % 2 ? $jack : $uni, $user, ['title' => 'Rendez-vous '.$i, 'starts_at' => now()->subDays($i)->setTime(10, 0)]));

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard')->assertSee('data-to-validate-all', false)->getContent();
        $this->assertCount(3, $this->ids($html, 'data-todo-event'), 'Trois lignes sur l\'accueil.');
        $this->assertStringContainsString('data-to-validate-bar>4<', $html);

        // Page complète, et filtre par cheval (puces en haut de page).
        $html = $this->actingAs($user)->get(route('todo.index'))->assertOk()->assertSee('data-horse-switch', false)->getContent();
        $this->assertCount(4, $this->ids($html, 'data-todo-event'));
        $html = $this->actingAs($user)->get(route('todo.index', ['horse' => $uni->id]))->assertOk()->assertSee('Uni · rendez-vous et séances passés')->getContent();
        $this->assertEqualsCanonicalizing($events->filter(fn ($e) => $e->horse_id === $uni->id)->pluck('id')->all(), $this->ids($html, 'data-todo-event'));

        // Cheval actif : l'accueil du cheval ne montre que lui, la pastille aussi.
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $jack->id]);
        $user->unsetRelation('profile');
        app()->forgetInstance(HorseAccess::class);
        $html = $this->actingAs($user)->get(route('dashboard'))->assertViewIs('dashboard-solo')->getContent();
        $this->assertStringContainsString('data-to-validate-bar>2<', $html);
        $this->assertCount(2, $this->ids($html, 'data-todo-event'));
    }

    public function test_treatment_markers_and_session_mirror_events_are_not_listed_twice(): void
    {
        Carbon::setTestNow('2026-10-17 12:00');
        $user = $this->user();
        $horse = $this->horseFor($user);
        Treatment::forceCreate(['horse_id' => $horse->id, 'author_id' => $user->id, 'product' => 'Vermifuge', 'starts_on' => now()->subDays(3)->toDateString(), 'status' => 'active']);
        $session = $this->ridingSession($horse, $user);
        $html = $this->actingAs($user)->get(route('todo.index'))->assertOk()->getContent();
        $this->assertSame([], $this->ids($html, 'data-todo-event'), 'Ni repère de traitement, ni événement miroir de la séance.');
        $this->assertSame([$session->id], $this->ids($html, 'data-todo-session'));
    }
}
