<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\HalfLease;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\RidingDay;
use App\Models\RidingDayChange;
use App\Models\RidingSession;
use App\Models\User;
use App\Services\HorseAccess;
use App\Services\RestDays;
use App\Services\RidingDays;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Jours de repos : dans les jours de monte (semaine type, une date) et dans l'agenda (« Repos »), séance signalée. */
class Version250RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
        app()->forgetInstance(RestDays::class);
        app()->forgetInstance(RidingDays::class);
    }

    /** Uni (Paul) partagé avec Léa en demi-pension le mardi ; aujourd'hui samedi 17 octobre 2026. */
    private function setUpHorse(): array
    {
        Carbon::setTestNow('2026-10-17 18:00');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $lea->id, 'label' => 'Demi-pension', 'permissions' => Perm::halfLeasePreset()]);
        RidingDay::create(['horse_id' => $horse->id, 'weekday' => 2, 'user_id' => $lea->id]);
        $this->fresh();

        return [$owner, $horse, $lea];
    }

    private function planSession(User $by, Horse $horse, string $at, string $type = 'ride')
    {
        $this->fresh();

        return $this->actingAs($by)->post(route('sessions.store'), ['horse_id' => $horse->id, 'scheduled_at' => $at, 'session_type' => $type, 'planned_minutes' => 60]);
    }

    private function titles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }

    public function test_weekly_rest_day_and_session_alerts(): void
    {
        [$owner, $horse, $lea] = $this->setUpHorse();
        // Lundi : repos dans la semaine type.
        $this->actingAs($owner)->put(route('horses.riding-days.update', $horse), ['days' => [1 => 'rest', 2 => $lea->id]])->assertSessionHasNoErrors();
        $this->assertTrue(RidingDay::where('weekday', 1)->sole()->is_rest);
        $this->assertNull(RidingDay::where('weekday', 1)->sole()->user_id);
        $this->fresh();
        $this->actingAs($lea)->get(route('horses.riding-days', $horse))->assertOk()->assertSee('data-riding-rest', false);
        // Le formulaire de séance le sait avant l'enregistrement.
        $this->actingAs($lea)->get(route('sessions.create', ['horse' => $horse->id]))->assertOk()->assertSee('repos prévu dans les jours de monte');

        // Balade de Léa lundi 19 : signalée, Paul prévenu, rappel sur la fiche et dans l'agenda.
        $this->planSession($lea, $horse, '2026-10-19 08:00')->assertSessionHas('warning', fn ($w) => str_contains($w, 'Lundi 19 octobre est un jour de repos pour Uni') && str_contains($w, 'le propriétaire est prévenu'));
        $this->assertContains('Séance un jour de repos : Uni', $this->titles($owner));
        $session = RidingSession::sole();
        $this->fresh();
        $this->actingAs($owner)->get(route('sessions.show', $session))->assertSee('data-session-rest', false)->assertSee('est un jour de repos pour Uni');
        $this->actingAs($owner)->get(route('calendar.index', ['view' => 'week', 'date' => '2026-10-19']))->assertOk()->assertSee('data-rest-badge', false)->assertSee('Repos');
        // Mardi : pas de repos, pas d'alerte.
        $this->planSession($lea, $horse, '2026-10-20 08:00')->assertSessionMissing('warning');
        // Déplacée un jour de repos : signalée aussi.
        $tuesday = RidingSession::latest('id')->first();
        $this->actingAs($lea)->put(route('sessions.update', $tuesday), ['scheduled_at' => '2026-10-26 08:00', 'session_type' => 'ride', 'planned_minutes' => 60])
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Lundi 26 octobre est un jour de repos'));
    }

    public function test_rest_for_one_date_set_and_lifted_by_the_owner(): void
    {
        [$owner, $horse, $lea] = $this->setUpHorse();
        // Mardi 20 (jour de Léa) : repos posé par Paul ; Léa est prévenue.
        $this->actingAs($owner)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-20', 'to' => 'rest'])->assertSessionHas('success', 'Mardi 20 octobre : jour de repos pour Uni.');
        $this->assertTrue(RidingDayChange::sole()->is_rest);
        $this->assertContains('Jour de monte : mardi 20 octobre', $this->titles($lea));
        $this->fresh();
        $this->assertNotEmpty(app(RestDays::class)->on($horse, Carbon::parse('2026-10-20')));
        // Léa ne lève pas le repos elle-même : elle demande le jour.
        $this->actingAs($lea)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-20', 'to' => $lea->id])->assertSessionHasErrors('date');
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-20'])->assertSessionHas('success');
        $this->assertTrue($owner->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'], 'mardi 20 octobre (jour de repos)')));
        // Paul rend le jour à Léa : plus de repos ce jour-là.
        $this->actingAs($owner)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-20', 'to' => $lea->id]);
        $this->fresh();
        $this->assertSame([], app(RestDays::class)->on($horse, Carbon::parse('2026-10-20')));
        $this->assertSame(0, RidingDayChange::count(), 'Retour au jour habituel : l\'échange disparaît.');
    }

    public function test_recurring_rest_event_of_the_agenda(): void
    {
        [$owner, $horse] = $this->setUpHorse();
        // « Repos » chaque lundi, toute la journée (comme sur la capture).
        $this->actingAs($owner)->post(route('calendar.store'), ['horse_id' => $horse->id, 'type' => 'rest', 'title' => 'Repos', 'all_day' => 1,
            'date' => '2026-10-12', 'recurrence' => 'weekly', 'recurrence_interval' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, CalendarEvent::where('type', 'rest')->count());
        $this->planSession($owner, $horse, '2026-10-19 08:00')->assertSessionHas('warning', fn ($w) => str_contains($w, 'jour de repos pour Uni (« Repos » dans l\'agenda)'));
        $this->assertSame(0, collect($this->titles($owner))->filter(fn ($t) => str_starts_with($t, 'Séance un jour de repos'))->count(), 'Pas de notification à soi-même.');
        // Exception de la série : ce lundi-là n'est plus un repos.
        $event = CalendarEvent::where('type', 'rest')->sole();
        $event->exclude('2026-10-26');
        $event->save();
        $this->planSession($owner, $horse, '2026-10-26 08:00')->assertSessionMissing('warning');

        // Repos posé un jour où une séance est déjà prévue : signalé.
        $this->actingAs($owner)->post(route('calendar.store'), ['horse_id' => $horse->id, 'type' => 'rest', 'title' => 'Repos (boiterie)', 'all_day' => 1,
            'date' => '2026-10-26'])->assertSessionHas('warning', fn ($w) => str_contains($w, '1 séance déjà prévue un jour de repos : lundi 26 octobre à 08:00'));
    }

    public function test_half_lease_page_for_someone_already_sharing_the_horse(): void
    {
        Carbon::setTestNow('2026-10-17 18:00');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'decouverte'); // 1 partage au plus
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $grant = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $lea->id, 'label' => 'Partage', 'permissions' => [Perm::HORSE_VIEW], 'granted_by' => $owner->id]);
        // Sa fiche reprend l'accès existant : pas de partage en plus, pas de second accès.
        $this->actingAs($owner)->post(route('horses.half-leases.store', $horse), [
            'name' => 'Léa DURAND', 'email' => 'lea@exemple.fr', 'starts_on' => '2026-10-01', 'weekdays' => [3], 'monthly_amount' => '0', 'permissions' => Perm::halfLeasePreset(),
        ])->assertSessionHasNoErrors();
        $lease = HalfLease::sole();
        $this->actingAs($lea)->post(route('invitations.respond', $lease->invitation_id), ['decision' => 'accept']);
        $this->assertSame(1, HorseAccessGrant::count());
        $this->assertSame(['active', $grant->id, 'Demi-pension'], [$lease->fresh()->status, $lease->fresh()->grant_id, $grant->fresh()->label]);
        $this->assertEqualsCanonicalizing(Perm::halfLeasePreset(), $grant->fresh()->permissions);
        $this->assertSame([3 => $lea->id], RidingDay::pluck('user_id', 'weekday')->all());
    }
}
