<?php

namespace Tests\Feature;

use App\Models\BoardingStatement;
use App\Models\HalfLease;
use App\Models\Horse;
use App\Models\Income;
use App\Models\Professional;
use App\Models\RecurringIncome;
use App\Models\User;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Mensualités de demi-pension : recette du propriétaire, échéances vues par la demi-pension, relevé pour un cheval d'écurie. */
class Version247RequestsTest extends TestCase
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

    /** Proposition de Paul acceptée par Léa (compte existant). */
    private function accepted(User $owner, Horse $horse, User $lea, array $data = []): HalfLease
    {
        $this->actingAs($owner)->post(route('horses.half-leases.store', $horse), array_merge([
            'name' => 'Léa DURAND', 'email' => $lea->email, 'starts_on' => '2026-11-01', 'notice_days' => 30, 'weekdays' => [2, 4],
            'monthly_amount' => '180', 'payment_day' => 5, 'first_month' => 'full', 'permissions' => Perm::halfLeasePreset(),
        ], $data))->assertSessionHasNoErrors();
        $lease = HalfLease::latest('id')->first();
        $this->actingAs($lea)->post(route('invitations.respond', $lease->invitation_id), ['decision' => 'accept']);
        $this->fresh();

        return $lease->fresh();
    }

    private function setUpOwner(string $now = '2026-10-17 18:00'): array
    {
        Carbon::setTestNow($now);
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);

        return [$owner, $horse, $lea];
    }

    private function titles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }

    public function test_monthly_fee_in_the_owner_budget_seen_by_the_half_lease(): void
    {
        [$owner, $horse, $lea] = $this->setUpOwner();
        $lease = $this->accepted($owner, $horse, $lea);
        $rec = RecurringIncome::sole();
        $this->assertSame([$lease->id, 'half_lease', '180.00', '2026-11-05', 'Léa DURAND'], [$rec->half_lease_id, $rec->category, $rec->amount, $rec->next_due_on->toDateString(), $rec->payer]);
        $this->assertSame(0, Income::count(), 'Rien de dû avant la première échéance.');
        $this->actingAs($owner)->get(route('half-leases.show', $lease))->assertSee('data-half-lease-fees', false)->assertSee('Prochaine mensualité : <strong class="text-slate-900">180,00 €</strong> le 5 novembre 2026', false);

        // 5 novembre : l'échéance arrive dans le budget, la demi-pension est prévenue.
        Carbon::setTestNow('2026-11-05 07:00');
        $this->artisan('expenses:recurring')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();
        $income = Income::sole();
        $this->assertSame([$lease->id, '2026-11-05', null], [$income->half_lease_id, $income->received_on->toDateString(), $income->collected_on]);
        $this->assertContains('Demi-pension de novembre : 180,00 €', $this->titles($lea));
        $this->artisan('reminders:send');
        $this->assertSame(1, collect($this->titles($lea))->filter(fn ($t) => str_starts_with($t, 'Demi-pension de novembre'))->count(), 'Prévenue une fois.');

        // Côté demi-pension : « Mes frais » et Budget, sans pouvoir noter la mensualité reçue.
        $this->fresh();
        $this->actingAs($lea)->get(route('lesson-costs.index'))->assertOk()->assertSee('Mes frais')->assertSee('data-costs-half-lease', false)
            ->assertSee('Novembre 2026')->assertSee('À régler')->assertSee('180,00 €');
        $this->actingAs($lea)->get(route('budget.index'))->assertOk()->assertSee('data-budget-half-lease', false)->assertSee('Novembre 2026 : à régler');
        $this->actingAs($lea)->post(route('budget.incomes.receive', $income), ['received' => 1])->assertForbidden();
        $this->actingAs($lea)->get(route('half-leases.show', $lease))->assertSee('À régler')->assertDontSee('data-fee-receive', false);

        // Le propriétaire la note reçue depuis la fiche : la demi-pension voit « Payée ».
        $this->fresh();
        $this->actingAs($owner)->post(route('budget.incomes.receive', $income), ['received' => 1])->assertSessionHas('success');
        $this->fresh();
        $this->actingAs($lea)->get(route('lesson-costs.index'))->assertSee('Payée');

        // Décembre non reçu 7 jours après l'échéance : le propriétaire est prévenu.
        Carbon::setTestNow('2026-12-05 07:00');
        $this->artisan('expenses:recurring');
        Carbon::setTestNow('2026-12-12 07:00');
        $this->artisan('reminders:send');
        $this->assertContains('Demi-pension non reçue : Léa DURAND', $this->titles($owner));
    }

    public function test_first_month_prorata_and_month_without_fee(): void
    {
        [$owner, $horse, $lea] = $this->setUpOwner('2026-10-19 09:00');
        $lease = $this->accepted($owner, $horse, $lea, ['starts_on' => '2026-10-19', 'first_month' => 'prorata']);
        $first = Income::sole();
        $this->assertSame(['2026-10-19', '75.48'], [$first->received_on->toDateString(), $first->amount], '13 jours sur 31.');
        $this->assertStringContainsString('premier mois (13 jours sur 31', $first->comment);
        $this->assertSame('2026-11-05', RecurringIncome::sole()->next_due_on->toDateString());

        // Pas de mensualité en décembre (vacances) : rien ce mois-là, janvier revient.
        $this->actingAs($owner)->post(route('half-leases.skip-month', $lease), ['month' => '2026-12'])->assertSessionHas('success');
        $this->actingAs($owner)->get(route('half-leases.show', $lease))->assertSee('Sans mensualité : décembre 2026');
        Carbon::setTestNow('2027-01-06 07:00');
        $this->artisan('expenses:recurring');
        $this->assertSame(['2026-10-19', '2026-11-05', '2027-01-05'], Income::orderBy('received_on')->pluck('received_on')->map->toDateString()->all());

        // Nouveau tarif : il vaut pour les mensualités suivantes.
        $this->actingAs($owner)->put(route('half-leases.update', $lease), [
            'name' => 'Léa DURAND', 'starts_on' => '2026-10-19', 'notice_days' => 30, 'weekdays' => [2, 4], 'monthly_amount' => '200', 'payment_day' => 5,
            'first_month' => 'prorata', 'permissions' => Perm::halfLeasePreset(),
        ])->assertSessionHasNoErrors();
        Carbon::setTestNow('2027-02-05 07:00');
        $this->artisan('expenses:recurring');
        $this->assertSame(['180.00', '200.00'], Income::orderBy('received_on')->get()->slice(-2)->pluck('amount')->values()->all());

        // Fin de la demi-pension fin février : plus de mensualité ensuite.
        $this->actingAs($owner)->post(route('half-leases.end', $lease), ['last_day' => '2027-02-28']);
        Carbon::setTestNow('2027-03-06 07:00');
        $this->artisan('expenses:recurring');
        $this->assertSame(0, Income::where('received_on', '>=', '2027-03-01')->count());
    }

    public function test_full_first_month_and_free_half_lease(): void
    {
        [$owner, $horse, $lea] = $this->setUpOwner('2026-10-19 09:00');
        $this->accepted($owner, $horse, $lea, ['starts_on' => '2026-10-19']);
        $this->assertSame('180.00', Income::sole()->amount, 'Premier mois complet.');

        // Demi-pension sans contrepartie : pas de recette.
        $this->grantLicense($owner->personalOrganization(), 'multi-chevaux');
        $other = $this->horseFor($owner, null, ['official_name' => 'AUTRE']);
        $ines = $this->user(['email' => 'ines@exemple.fr']);
        $this->accepted($owner, $other, $ines, ['email' => 'ines@exemple.fr', 'monthly_amount' => '0', 'starts_on' => '2026-10-19']);
        $this->assertSame(1, RecurringIncome::count());
    }

    public function test_stable_horse_fee_on_the_monthly_statement(): void
    {
        Carbon::setTestNow('2026-10-17 18:00');
        $manager = $this->user(['name' => 'Marc ROUX']);
        $stable = $this->stable($manager, 'Écurie des Pins');
        $manager->forceFill(['current_organization_id' => $stable->id])->save();
        $manager = $manager->fresh();
        $prince = $this->horseFor($manager, $stable, ['official_name' => 'PRINCE', 'usual_name' => 'Prince']);
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $lease = $this->accepted($manager, $prince, $lea, ['starts_on' => '2026-10-10', 'first_month' => 'prorata', 'monthly_amount' => '155']);
        $this->assertSame(0, RecurringIncome::count(), 'Cheval de l\'écurie : pas de recette, la mensualité va sur le relevé.');
        // Un cours avec l'enseignant de l'écurie, au nom de Léa.
        $jimmy = Professional::forceCreate(['organization_id' => $stable->id, 'first_name' => 'Jimmy', 'last_name' => 'BLANC', 'kind' => 'instructor']);
        $jimmy->rates()->create(['kind' => 'private_lesson', 'unit' => 'session', 'amount' => 40]);
        $this->actingAs($manager)->post(route('sessions.store'), ['horse_id' => $prince->id, 'scheduled_at' => '2026-10-14 10:00', 'session_type' => 'private_lesson',
            'planned_minutes' => 45, 'instructor' => 'Jimmy BLANC', 'rider_id' => $lea->id])->assertSessionHasNoErrors();

        $this->actingAs($manager)->post(route('boarding.generate'), ['period' => '2026-10'])->assertSessionHas('success');
        $statement = BoardingStatement::where('owner_user_id', $lea->id)->sole();
        $this->assertSame(['Relevé de demi-pension et de cours', ['half_lease', 'lesson'], '150.00'], [$statement->title(), array_column($statement->lines, 'kind'), $statement->total]);
        $this->assertSame('22 jours sur 31 (10/10 – 31/10)', $statement->lines[0]['detail'], '155 € × 22/31 = 110 €.');
        $this->actingAs($manager)->post(route('boarding.send', $statement));
        $this->fresh();
        $this->actingAs($lea)->get(route('half-leases.show', $lease))->assertSee('Octobre 2026')->assertSee('Relevé n° '.$statement->number)->assertSee('À régler');
        $this->actingAs($manager)->post(route('boarding.pay', $statement), ['paid_on' => '2026-10-17']);
        $this->actingAs($lea)->get(route('lesson-costs.index'))->assertSee('Payée');
        // Mois sans mensualité : pas de ligne de demi-pension sur le relevé de novembre.
        $this->actingAs($manager)->post(route('half-leases.skip-month', $lease), ['month' => '2026-11']);
        $this->actingAs($manager)->post(route('boarding.generate'), ['period' => '2026-11']);
        $this->assertSame(0, BoardingStatement::where('period', '2026-11')->where('owner_user_id', $lea->id)->count());
    }
}
