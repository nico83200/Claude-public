<?php

namespace Tests\Feature;

use App\Models\HorseAccessGrant;
use App\Models\RidingDayChange;
use App\Models\RidingSession;
use App\Models\User;
use App\Services\HorseAccess;
use App\Services\RidingDays;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Jours de monte d'un cheval partagé : semaine type, agenda, accueil, échanges ponctuels, séance sur le jour d'un autre. */
class Version241RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Propriétaire (offre avec partage) et sa demi-pension Léa. */
    private function shared(string $plan = 'particulier'): array
    {
        Carbon::setTestNow('2026-10-17 09:00'); // samedi
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), $plan);
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $lea = $this->user(['name' => 'Léa DURAND']);
        $grant = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $lea->id, 'permissions' => Perm::halfLeasePreset(), 'label' => 'Demi-pension']);

        return [$owner, $horse, $lea, $grant];
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
        app()->forgetInstance(RidingDays::class);
    }

    private function ridingNotes(User $user)
    {
        return $user->notifications()->get()->filter(fn ($n) => ($n->data['kind'] ?? null) === 'riding_day')->values();
    }

    /** Notification « jours de monte » reçue avec ce texte (l'ordre de deux envois dans la même seconde n'est pas garanti). */
    private function noteWith(User $user, string $text)
    {
        return $this->ridingNotes($user)->first(fn ($n) => str_contains($n->data['title'].' '.$n->data['body'], $text));
    }

    public function test_owner_sets_the_week_and_the_half_leaser_is_told(): void
    {
        [$owner, $horse, $lea] = $this->shared();
        $this->actingAs($owner)->get(route('horses.riding-days', $horse))->assertOk()->assertSee('data-riding-week-form', false)->assertSee('Léa DURAND');
        // Lundi et jeudi à Léa, mercredi au propriétaire.
        $this->actingAs($owner)->put(route('horses.riding-days.update', $horse), ['days' => [1 => $lea->id, 3 => $owner->id, 4 => $lea->id]])
            ->assertSessionHas('success');
        $this->fresh();
        $week = app(RidingDays::class)->weekly($horse);
        $this->assertSame([1 => $lea->id, 3 => $owner->id, 4 => $lea->id], collect($week)->map->id->all());
        $this->assertNotNull($this->noteWith($lea, 'Vos jours de monte avec Uni : lundi et jeudi.'));
        $this->assertCount(0, $this->ridingNotes($owner), 'Celui qui modifie n\'est pas prévenu.');
        // Page de partage : le résumé.
        $this->actingAs($owner)->get(route('horses.sharing', $horse))->assertOk()->assertSee('data-sharing-riding-days', false)->assertSee('Lundi et jeudi');

        // Quelqu'un sans accès au cheval ne peut pas recevoir de jour ; la demi-pension ne règle pas la semaine.
        $stranger = $this->user();
        $this->actingAs($owner)->put(route('horses.riding-days.update', $horse), ['days' => [5 => $stranger->id]])->assertSessionHasErrors('days.5');
        $this->actingAs($lea)->put(route('horses.riding-days.update', $horse), ['days' => [1 => $lea->id, 2 => $lea->id]])->assertForbidden();
        $this->actingAs($lea)->get(route('horses.riding-days', $horse))->assertOk()->assertDontSee('data-riding-week-form', false);
    }

    public function test_days_show_in_the_agenda_and_on_the_home_page(): void
    {
        [$owner, $horse, $lea] = $this->shared();
        app(RidingDays::class)->setWeekly($horse, [1 => $lea->id, 3 => $owner->id], $owner);
        $this->fresh();

        // Agenda (semaine du 19 octobre) : les jours attribués apparaissent même sans rendez-vous.
        $html = $this->actingAs($owner)->get(route('calendar.index', ['view' => 'week', 'date' => '2026-10-19']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#data-riding-day="2026-10-19"[^>]*>Jour de Léa DURAND#', $html);
        $this->assertMatchesRegularExpression('#data-riding-day="2026-10-21"[^>]*>Mon jour#', $html);
        $this->assertStringNotContainsString('data-riding-day="2026-10-20"', $html);
        // Côté Léa : « Mon jour » le lundi.
        $this->fresh();
        $html = $this->actingAs($lea)->get(route('calendar.index', ['view' => 'week', 'date' => '2026-10-19']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#data-riding-day="2026-10-19"[^>]*>Mon jour#', $html);

        // Accueil du cheval : bande des 7 prochains jours.
        $this->fresh();
        $this->actingAs($lea)->get(route('dashboard'))->assertOk()->assertSee('data-solo-riding', false)
            ->assertSee('data-solo-riding-day="2026-10-19"', false)->assertSee('Aujourd\'hui : <strong>jour libre</strong>', false);
        Carbon::setTestNow('2026-10-19 09:00');
        $this->fresh();
        $this->actingAs($lea)->get(route('dashboard'))->assertSee('Aujourd\'hui : <strong>votre jour de monte</strong>', false);
    }

    public function test_give_a_day_or_leave_it_free(): void
    {
        [$owner, $horse, $lea] = $this->shared();
        app(RidingDays::class)->setWeekly($horse, [1 => $lea->id, 3 => $owner->id], $owner);
        $this->fresh();

        // Léa laisse son lundi 19 libre : le propriétaire est prévenu ; l'agenda le montre.
        $this->actingAs($lea)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-19'])->assertSessionHas('success', 'Lundi 19 octobre laissé libre.');
        $this->fresh();
        $this->assertNull(app(RidingDays::class)->on($horse, '2026-10-19')['user']);
        $this->assertNotNull($this->noteWith($owner, 'Léa DURAND laisse lundi 19 octobre libre pour Uni.'));
        $this->actingAs($owner)->get(route('calendar.index', ['view' => 'week', 'date' => '2026-10-19']))->assertSee('Jour libre · échange');

        // Léa ne peut pas céder le mercredi du propriétaire, ni un jour passé.
        $this->actingAs($lea)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-21', 'to' => $lea->id])->assertSessionHasErrors('date');
        $this->actingAs($lea)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-12'])->assertSessionHasErrors('date');

        // Le propriétaire laisse son mercredi 21 à Léa : Léa est prévenue.
        $this->actingAs($owner)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-21', 'to' => $lea->id])->assertSessionHas('success');
        $this->fresh();
        $this->assertSame($lea->id, app(RidingDays::class)->on($horse, '2026-10-21')['user']->id);
        $this->assertNotNull($this->noteWith($lea, 'Paul MARTIN vous laisse mercredi 21 octobre avec Uni.'));
        // Et le reprend : retour au jour habituel, l'échange disparaît.
        $this->actingAs($owner)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-21', 'to' => $owner->id]);
        $this->assertFalse(RidingDayChange::whereDate('date', '2026-10-21')->exists());

        // Page des jours : Léa voit « Laisser ce jour » sur ses jours.
        $this->fresh();
        $this->actingAs($lea)->get(route('horses.riding-days', $horse))->assertOk()->assertSee('data-riding-date="2026-10-26"', false)->assertSee('Laisser ce jour');
    }

    public function test_session_planned_on_someone_elses_day(): void
    {
        [$owner, $horse, $lea] = $this->shared();
        app(RidingDays::class)->setWeekly($horse, [1 => $lea->id, 3 => $owner->id], $owner);
        $this->fresh();

        // Formulaire : l'aide connaît les jours des prochaines semaines.
        $this->actingAs($owner)->get(route('sessions.create', ['horse' => $horse->id]))->assertOk()->assertSee('data-riding-hint', false)
            ->assertSee('2026-10-19', false);

        // Séance prévue lundi (jour de Léa) par le propriétaire : Léa est prévenue, avertissement affiché.
        $data = ['horse_id' => $horse->id, 'session_type' => 'free', 'planned_minutes' => 45];
        $this->actingAs($owner)->post(route('sessions.store'), $data + ['scheduled_at' => '2026-10-19 18:00'])
            ->assertSessionHas('warning', 'C\'est le jour de monte de Léa DURAND : une notification lui a été envoyée.');
        $this->assertNotNull($this->noteWith($lea, 'Paul MARTIN a prévu une séance avec Uni lundi 19 octobre à 18:00, votre jour de monte.'));

        // Mercredi (son propre jour) ou une séance de Léa sur son lundi : personne n'est dérangé.
        $before = $this->ridingNotes($lea)->count();
        $this->actingAs($owner)->post(route('sessions.store'), $data + ['scheduled_at' => '2026-10-21 18:00'])->assertSessionMissing('warning');
        $this->fresh();
        $this->actingAs($lea)->post(route('sessions.store'), $data + ['scheduled_at' => '2026-10-26 10:00'])->assertSessionMissing('warning');
        $this->assertSame($before, $this->ridingNotes($lea)->count());
        $this->assertSame(0, $this->ridingNotes($owner)->filter(fn ($n) => $n->data['title'] === 'Séance prévue sur votre jour')->count());

        // Séance déplacée sur le jour de Léa : elle est prévenue aussi.
        $moved = RidingSession::where('scheduled_at', '2026-10-21 18:00:00')->sole();
        $this->actingAs($owner)->put(route('sessions.update', $moved), $data + ['scheduled_at' => '2026-10-26 18:00', 'status' => 'planned'])->assertSessionHas('warning');
        $this->assertSame($before + 1, $this->ridingNotes($lea)->count());
    }

    public function test_horse_chips_open_the_other_horses_days(): void
    {
        [$owner, $horse] = $this->shared('multi-chevaux');
        $jack = $this->horseFor($owner, null, ['official_name' => 'JACK', 'usual_name' => 'Jack']);
        $this->fresh();
        $html = $this->actingAs($owner)->get(route('horses.riding-days', $horse))->assertOk()->assertSee('data-horse-switch', false)->getContent();
        $this->assertMatchesRegularExpression('#aria-current="true"[^>]*data-horse-switch-chip="'.$horse->id.'"#', $html);
        $this->actingAs($owner)->post(route('focus.set'), ['horse' => $jack->id, 'back' => 'horses.riding-days'])->assertRedirect(route('horses.riding-days', $jack));
        $this->actingAs($owner)->post(route('focus.set'), ['horse' => 'all', 'back' => 'horses.riding-days'])->assertRedirect(route('calendar.index'));
    }

    public function test_ended_share_frees_the_days_and_rights_are_checked(): void
    {
        [$owner, $horse, $lea, $grant] = $this->shared();
        app(RidingDays::class)->setWeekly($horse, [1 => $lea->id], $owner);
        $grant->update(['revoked_at' => now()]);
        $this->fresh();
        $this->assertSame([], app(RidingDays::class)->weekly($horse), 'Partage terminé : ses jours sont libres.');

        $reader = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $reader->id, 'permissions' => [Perm::HORSE_VIEW]]);
        $this->fresh();
        $this->actingAs($reader)->get(route('horses.riding-days', $horse))->assertForbidden();
        $this->actingAs($reader)->post(route('horses.riding-days.give', $horse), ['date' => '2026-10-19'])->assertSessionHasErrors('date');
    }
}
