<?php

namespace Tests\Feature;

use App\Models\HalfLease;
use App\Models\Horse;
use App\Models\RidingDay;
use App\Models\RidingDayChange;
use App\Models\RidingDayRequest;
use App\Models\RidingSession;
use App\Models\User;
use App\Services\HorseAccess;
use App\Services\RidingDays;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Jours de monte : demander un jour, échanger, absence en une fois, jours prévus et séances notées du mois. */
class Version249RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
    }

    /** Uni : Léa en demi-pension le mardi et le jeudi, Paul le mercredi et le samedi. Aujourd'hui : samedi 17 octobre 2026. */
    private function setUpWeek(): array
    {
        Carbon::setTestNow('2026-10-17 18:00');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $this->actingAs($owner)->post(route('horses.half-leases.store', $horse), [
            'name' => 'Léa DURAND', 'email' => 'lea@exemple.fr', 'starts_on' => '2026-10-01', 'weekdays' => [2, 4], 'monthly_amount' => '0', 'permissions' => Perm::halfLeasePreset(),
        ])->assertSessionHasNoErrors();
        $this->actingAs($lea)->post(route('invitations.respond', HalfLease::sole()->invitation_id), ['decision' => 'accept']);
        RidingDay::create(['horse_id' => $horse->id, 'weekday' => 3, 'user_id' => $owner->id]);
        RidingDay::create(['horse_id' => $horse->id, 'weekday' => 6, 'user_id' => $owner->id]);
        $this->fresh();

        return [$owner, $horse, $lea];
    }

    private function holder(Horse $horse, string $date): ?int
    {
        $this->fresh();

        return app(RidingDays::class)->on($horse, $date)['user']?->id;
    }

    private function titles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }

    public function test_request_a_day_then_accept_from_the_week_page(): void
    {
        [$owner, $horse, $lea] = $this->setUpWeek();
        $this->actingAs($lea)->get(route('horses.riding-days', $horse))->assertOk()->assertSee('data-riding-ask-open', false)->assertSee('Demander à Paul MARTIN');
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-24'])->assertSessionHas('success');
        $this->assertContains('Demande de jour : samedi 24 octobre', $this->titles($owner));
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-24'])->assertSessionHasErrors('date');
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-20'])->assertSessionHasErrors('date'); // déjà son jour

        $this->fresh();
        $this->actingAs($owner)->get(route('horses.riding-days', $horse))->assertSee('Léa DURAND</strong> aimerait monter samedi 24 octobre', false);
        $request = RidingDayRequest::sole();
        $this->actingAs($owner)->post(route('horses.riding-days.answer', [$horse, $request]), ['decision' => 'accept'])->assertSessionHas('success');
        $this->assertSame([$lea->id, 'accepted'], [$this->holder($horse, '2026-10-24'), $request->fresh()->status]);
        $this->assertContains('Jour de monte : samedi 24 octobre', $this->titles($lea));
    }

    public function test_swap_two_days_or_decline(): void
    {
        [$owner, $horse, $lea] = $this->setUpWeek();
        // Mercredi 21 (Paul) contre mardi 20 (Léa).
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-21', 'swap_date' => '2026-10-25'])->assertSessionHasErrors('swap_date'); // pas un de ses jours
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-21', 'swap_date' => '2026-10-20'])->assertSessionHas('success');
        $this->assertContains('Demande de jour : mercredi 21 octobre', $this->titles($owner));
        // Une tierce personne ne répond pas à la place de Paul.
        $other = $this->user();
        $this->actingAs($other)->post(route('horses.riding-days.answer', [$horse, RidingDayRequest::sole()]), ['decision' => 'accept'])->assertForbidden();
        $this->actingAs($owner)->post(route('horses.riding-days.answer', [$horse, RidingDayRequest::sole()]), ['decision' => 'accept'])->assertSessionHas('success');
        $this->assertSame([$lea->id, $owner->id], [$this->holder($horse, '2026-10-21'), $this->holder($horse, '2026-10-20')]);
        $this->assertContains('Jour de monte : mardi 20 octobre', $this->titles($owner), 'Paul reçoit le mardi en retour.');

        // Refus : Léa est prévenue, rien ne change.
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-31']);
        $this->actingAs($owner)->post(route('horses.riding-days.answer', [$horse, RidingDayRequest::where('status', 'pending')->sole()]), ['decision' => 'decline']);
        $this->assertSame($owner->id, $this->holder($horse, '2026-10-31'));
        $this->assertTrue(collect($this->titles($lea))->contains('Jour de monte : samedi 31 octobre'));
        // Demande retirée par Léa.
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-11-07']);
        $pending = RidingDayRequest::where('status', 'pending')->sole();
        $this->actingAs($lea)->delete(route('horses.riding-days.cancel-request', [$horse, $pending]))->assertSessionHas('success');
        $this->assertSame('cancelled', $pending->fresh()->status);
    }

    public function test_free_day_goes_to_the_owner(): void
    {
        [$owner, $horse, $lea] = $this->setUpWeek();
        // Lundi : personne.
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-19'])->assertSessionHas('success');
        $this->assertNull(RidingDayRequest::sole()->holder_id);
        $this->assertContains('Demande de jour : lundi 19 octobre', $this->titles($owner));
        $this->actingAs($lea)->post(route('horses.riding-days.request', $horse), ['date' => '2026-10-26', 'swap_date' => '2026-10-20'])->assertSessionHasErrors('swap_date'); // pas d'échange pour un jour libre
        $this->actingAs($owner)->post(route('horses.riding-days.answer', [$horse, RidingDayRequest::sole()]), ['decision' => 'accept']);
        $this->assertSame($lea->id, $this->holder($horse, '2026-10-19'));
    }

    public function test_absence_frees_or_gives_days_at_once(): void
    {
        [$owner, $horse, $lea] = $this->setUpWeek();
        $this->actingAs($lea)->post(route('horses.riding-days.absence', $horse), ['from' => '2026-10-19', 'to' => '2026-10-31'])->assertSessionHas('success', '4 jours libérés sur la période.');
        $this->assertSame(4, RidingDayChange::whereNull('user_id')->where('from_user_id', $lea->id)->count(), 'Mardis et jeudis 20, 22, 27, 29.');
        $absences = collect($owner->notifications()->get())->filter(fn ($n) => $n->data['title'] === 'Absence : jours de monte de Uni');
        $this->assertSame(1, $absences->count(), 'Une seule notification.');
        $this->assertStringContainsString('4 jours libérés', $absences->first()->data['body']);

        // Paul part en vacances : ses jours laissés à Léa.
        $this->actingAs($owner)->post(route('horses.riding-days.absence', $horse), ['from' => '2026-11-02', 'to' => '2026-11-08', 'give_to' => $lea->id])->assertSessionHas('success');
        $this->assertSame([$lea->id, $lea->id], [$this->holder($horse, '2026-11-04'), $this->holder($horse, '2026-11-07')]);
        $this->assertContains('Absence : jours de monte de Uni', $this->titles($lea));
    }

    public function test_month_count_on_the_week_page_and_the_half_lease(): void
    {
        [$owner, $horse, $lea] = $this->setUpWeek();
        foreach (['2026-10-06 10:00', '2026-10-13 10:00'] as $at) {
            RidingSession::forceCreate(['horse_id' => $horse->id, 'created_by' => $lea->id, 'rider_id' => $lea->id, 'scheduled_at' => $at, 'status' => 'completed', 'session_type' => 'free']);
        }
        $this->actingAs($lea)->get(route('horses.riding-days', $horse))->assertOk()->assertSee('data-riding-month', false)->assertSee('9 jours prévus · 2 séances notées');
        $this->actingAs($lea)->get(route('half-leases.show', HalfLease::sole()))->assertSee('Octobre : 9 jours prévus · 2 séances notées');
    }
}
