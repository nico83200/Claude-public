<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Expense;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\InstructorRate;
use App\Models\LessonCharge;
use App\Models\Professional;
use App\Models\RidingSession;
use App\Models\User;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Frais de cours : tarifs des enseignants, coût de chaque cours au nom de la personne qui paie, budget du propriétaire seulement. */
class Version243RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setUpStable(): array
    {
        Carbon::setTestNow('2026-10-17 18:00');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $jimmy = $this->instructor($owner, [['private_lesson', 'session', 45, 40], ['private_lesson', 'hour', null, 50], ['group_lesson', 'session', null, 25], ['travel', 'visit', null, 15], ['coaching', 'round', null, 30]]);
        $lea = $this->user(['name' => 'Léa DURAND']);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $lea->id, 'label' => 'Demi-pension', 'permissions' => Perm::halfLeasePreset()]);

        return [$owner, $horse, $jimmy, $lea];
    }

    /** Enseignant « Jimmy BLANC » dans l'espace de la personne, avec ses tarifs [type, unité, durée, montant]. */
    private function instructor(User $user, array $rates): Professional
    {
        $pro = Professional::forceCreate(['organization_id' => $user->personalOrganization()->id, 'first_name' => 'Jimmy', 'last_name' => 'BLANC', 'kind' => 'instructor']);
        foreach ($rates as [$kind, $unit, $minutes, $amount]) {
            $pro->rates()->create(['kind' => $kind, 'unit' => $unit, 'duration_minutes' => $minutes, 'amount' => $amount]);
        }

        return $pro;
    }

    private function lesson(User $by, Horse $horse, string $at, array $extra = [])
    {
        return $this->actingAs($by)->post(route('sessions.store'), array_merge(['horse_id' => $horse->id, 'scheduled_at' => $at, 'session_type' => 'private_lesson', 'planned_minutes' => 45, 'instructor' => 'Jimmy BLANC'], $extra));
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
    }

    public function test_instructor_rates_on_the_professional_page(): void
    {
        [$owner, , , $lea] = $this->setUpStable();
        $pro = Professional::forceCreate(['organization_id' => $owner->personalOrganization()->id, 'first_name' => 'Sophie', 'last_name' => 'LOIR', 'kind' => 'instructor']);
        $this->actingAs($owner)->get(route('professionals.show', $pro))->assertOk()->assertSee('data-instructor-rates', false)->assertSee('data-rate-form', false);
        $this->actingAs($owner)->post(route('professionals.rates.store', $pro), ['kind' => 'private_lesson', 'unit' => 'session', 'duration_minutes' => 60, 'amount' => '45'])->assertSessionHas('success');
        $this->actingAs($owner)->post(route('professionals.rates.store', $pro), ['kind' => 'travel', 'unit' => 'visit', 'duration_minutes' => 30, 'amount' => '12'])->assertSessionHas('success');
        $this->assertNull($pro->rates()->where('kind', 'travel')->value('duration_minutes'), 'Pas de durée pour un déplacement.');
        // Unité qui ne convient pas : cours collectif « à l'heure ».
        $this->actingAs($owner)->post(route('professionals.rates.store', $pro), ['kind' => 'group_lesson', 'unit' => 'hour', 'amount' => '20'])->assertSessionHasErrors('unit');
        $this->actingAs($owner)->get(route('professionals.show', $pro))->assertSee('Cours particulier 60 min : 45,00 € par cours')->assertSee('Déplacement : 12,00 € par venue');
        // Quelqu'un d'autre ne touche pas aux tarifs.
        $this->actingAs($lea)->post(route('professionals.rates.store', $pro), ['kind' => 'private_lesson', 'unit' => 'session', 'amount' => '1'])->assertForbidden();
        $rate = $pro->rates()->firstWhere('kind', 'travel');
        $this->actingAs($owner)->delete(route('professionals.rates.destroy', [$pro, $rate]))->assertSessionHas('success');
        $this->assertSame(1, $pro->rates()->count());
    }

    public function test_owner_lesson_goes_to_his_budget_with_travel_once_a_day(): void
    {
        [$owner, $horse, $jimmy] = $this->setUpStable();
        $this->lesson($owner, $horse, '2026-10-17 09:00');
        $session = RidingSession::where('scheduled_at', '2026-10-17 09:00:00')->sole();
        $this->assertSame($jimmy->id, $session->professional_id, 'Enseignant relié à sa fiche.');
        $charges = LessonCharge::where('payer_id', $owner->id)->orderBy('kind', 'desc')->get();
        $this->assertSame([['travel', '15.00'], ['private_lesson', '40.00']], $charges->map(fn ($c) => [$c->kind, $c->amount])->all());
        $this->assertSame('Cours particulier 45 min avec Jimmy BLANC', $charges[1]->label);
        // Recopié dans le budget du cheval (catégorie « Cours et coaching »).
        $expenses = Expense::with('category')->where('horse_id', $horse->id)->get();
        $this->assertSame(['lessons', 'lessons'], $expenses->pluck('category.key')->all());
        $this->assertSame(55.0, (float) $expenses->sum('amount'));
        $this->actingAs($owner)->get(route('budget.index'))->assertOk()->assertSee('Cours et coaching');

        // Deuxième cours le même jour, d'une heure : tarif à l'heure, pas de second déplacement.
        $this->lesson($owner, $horse, '2026-10-17 11:00', ['planned_minutes' => 60]);
        $this->assertSame(['40.00', '50.00'], LessonCharge::where('kind', 'private_lesson')->orderBy('id')->pluck('amount')->all());
        $this->assertSame(1, LessonCharge::where('kind', 'travel')->count());

        // Page « Frais de cours » et carte de la séance.
        $this->actingAs($owner)->get(route('lesson-costs.index'))->assertOk()->assertSee('105,00 €')->assertSee('aussi dans le budget du cheval');
        $this->actingAs($owner)->get(route('sessions.show', $session))->assertOk()->assertSee('data-lesson-cost', false)->assertSee('Déplacement de Jimmy BLANC');
    }

    public function test_half_lease_lesson_costs_stay_in_her_space(): void
    {
        [$owner, $horse, , $lea] = $this->setUpStable();
        // Léa a son propre Jimmy, avec le prix qu'elle paie.
        $leaJimmy = $this->instructor($lea, [['private_lesson', 'session', 45, 42]]);
        $this->lesson($lea, $horse, '2026-10-16 17:00');
        $charge = LessonCharge::sole();
        $this->assertSame([$lea->id, $leaJimmy->id, '42.00', null], [$charge->payer_id, $charge->professional_id, $charge->amount, $charge->expense_id]);
        $this->assertSame(0, Expense::where('horse_id', $horse->id)->count(), 'Rien dans le budget du propriétaire.');

        // Le propriétaire ne voit pas ses frais ; Léa les voit et les marque payés.
        $session = RidingSession::sole();
        $this->fresh();
        $this->actingAs($owner)->get(route('sessions.show', $session))->assertOk()->assertDontSee('data-lesson-cost', false)->assertDontSee('42,00');
        $this->actingAs($owner)->get(route('lesson-costs.index'))->assertOk()->assertDontSee('42,00');
        $this->fresh();
        $this->actingAs($lea)->get(route('lesson-costs.index'))->assertOk()->assertSee('42,00 €');
        $this->actingAs($owner)->post(route('lesson-costs.paid', $charge), ['paid' => 1])->assertForbidden();
        $this->actingAs($lea)->post(route('lesson-costs.paid', $charge), ['paid' => 1])->assertSessionHas('success');
        $this->assertSame('2026-10-17', $charge->fresh()->paid_on->toDateString());
    }

    public function test_lesson_noted_for_the_half_lease_then_taken_by_the_owner(): void
    {
        [$owner, $horse, , $lea] = $this->setUpStable();
        $this->lesson($owner, $horse, '2026-10-16 17:00', ['rider_id' => $lea->id]);
        $session = RidingSession::sole();
        $this->assertSame([$lea->id, $lea->id], LessonCharge::orderBy('id')->pluck('payer_id')->all(), 'À la charge de la cavalière : cours et déplacement.');
        $this->assertSame(0, Expense::where('horse_id', $horse->id)->count());
        $note = $lea->notifications()->get()->first(fn ($n) => $n->data['kind'] === 'lesson_cost' && str_contains($n->data['body'], 'Cours particulier'));
        $this->assertStringContainsString('Paul MARTIN a noté « Cours particulier 45 min avec Jimmy BLANC » du 16 octobre avec Uni à votre charge : 40,00 €.', $note->data['body']);
        $this->actingAs($owner)->get(route('sessions.show', $session))->assertSee('À la charge de Léa DURAND');

        // Le propriétaire prend le cours à sa charge : frais déplacés chez lui, et dans son budget.
        $this->actingAs($owner)->put(route('sessions.cost', $session), ['payer_id' => $owner->id, 'lesson_price' => ''])->assertSessionHas('success');
        $this->assertSame([$owner->id], LessonCharge::pluck('payer_id')->unique()->values()->all());
        $this->assertSame(55.0, (float) Expense::where('horse_id', $horse->id)->sum('amount'));
        // Léa ne peut pas mettre un cours à la charge du propriétaire.
        $this->actingAs($lea)->put(route('sessions.cost', $session), ['payer_id' => $owner->id])->assertForbidden();
    }

    public function test_estimate_debrief_group_lesson_and_manual_price(): void
    {
        [$owner, $horse] = $this->setUpStable();
        // Cours prévu : coût prévu, rien de compté.
        $this->lesson($owner, $horse, '2026-10-20 10:00');
        $session = RidingSession::sole();
        $this->assertSame(0, LessonCharge::count());
        $this->actingAs($owner)->get(route('sessions.show', $session))->assertSee('data-lesson-estimate', false)->assertSee('Coût prévu : <strong>40,00 €</strong>', false);
        // Bilan : le cours est fait, il est compté.
        Carbon::setTestNow('2026-10-20 12:00');
        $this->actingAs($owner)->put(route('sessions.debrief.save', $session), ['actual_minutes' => 45, 'rider_feeling' => 4]);
        $this->assertSame('40.00', LessonCharge::where('kind', 'private_lesson')->sole()->amount);

        // Prix saisi, puis cours offert (0 €).
        $this->actingAs($owner)->put(route('sessions.cost', $session), ['payer_id' => $owner->id, 'lesson_price' => '35'])->assertSessionHas('success');
        $this->assertSame('35.00', LessonCharge::where('kind', 'private_lesson')->sole()->amount);
        $this->actingAs($owner)->put(route('sessions.cost', $session), ['payer_id' => $owner->id, 'lesson_price' => '0']);
        $this->assertSame(0, LessonCharge::where('kind', 'private_lesson')->count());
        $this->assertSame(0, LessonCharge::where('kind', 'travel')->count(), 'Plus de cours payant ce jour-là : plus de déplacement.');

        // Cours collectif : prix par cavalier.
        $this->lesson($owner, $horse, '2026-10-19 18:00', ['session_type' => 'group_lesson', 'planned_minutes' => 60]);
        $this->assertSame('25.00', LessonCharge::where('kind', 'group_lesson')->sole()->amount);
    }

    public function test_budget_payment_deletion_and_session_undo_follow_the_costs(): void
    {
        [$owner, $horse] = $this->setUpStable();
        $this->lesson($owner, $horse, '2026-10-17 09:00');
        $session = RidingSession::sole();
        $charge = LessonCharge::where('kind', 'private_lesson')->sole();
        $expense = Expense::findOrFail($charge->expense_id);

        // Payé dans le budget : payé dans les frais de cours.
        $this->actingAs($owner)->post(route('budget.pay', $expense), ['paid' => 1])->assertSessionHas('success');
        $this->assertSame('2026-10-17', $charge->fresh()->paid_on->toDateString());
        // Montant corrigé dans le budget : il devient le prix du cours.
        $expense->fresh()->update(['amount' => 38]);
        $this->assertSame(['38.00', '38.00'], [$charge->fresh()->amount, $session->fresh()->lesson_price]);

        // Séance supprimée puis rétablie : les frais aussi, payés comme avant.
        $this->actingAs($owner)->delete(route('sessions.destroy', $session));
        $this->assertSoftDeleted($charge);
        $this->assertSoftDeleted($expense);
        $this->actingAs($owner)->post(route('sessions.restore', $session));
        $this->assertNotSoftDeleted($charge);
        $this->assertSame('2026-10-17', $charge->fresh()->paid_on->toDateString());
        $this->assertNotSoftDeleted($expense);

        // Dépense supprimée dans le budget : frais retirés, et ils ne reviennent pas tout seuls ; « Rétablir » les ramène.
        $this->actingAs($owner)->delete(route('budget.destroy', $expense));
        $this->assertSoftDeleted($charge);
        $session->fresh()->forceFill(['location' => 'Manège'])->save();
        $this->assertSame(1, LessonCharge::withTrashed()->where('kind', 'private_lesson')->count());
        $this->assertSoftDeleted($charge);
        $this->actingAs($owner)->get(route('sessions.show', $session))->assertSee('data-charge-restore', false);
        $this->actingAs($owner)->post(route('lesson-costs.restore', $charge))->assertSessionHas('success');
        $this->assertNotSoftDeleted($charge);
        $this->assertNotSoftDeleted(Expense::findOrFail($charge->fresh()->expense_id));
    }

    public function test_coaching_on_a_validated_competition(): void
    {
        [$owner, $horse, $jimmy] = $this->setUpStable();
        $event = new CalendarEvent(['horse_id' => $horse->id, 'type' => 'competition', 'title' => 'CSO de Lyon', 'starts_at' => '2026-10-11 08:00', 'all_day' => true, 'professional_id' => $jimmy->id, 'coaching_rounds' => 2]);
        $event->organization_id = $horse->organization_id;
        $event->created_by = $owner->id;
        $event->save();
        $this->assertSame(0, LessonCharge::count(), 'Concours pas encore validé.');
        $this->actingAs($owner)->post(route('calendar.done', $event));
        $charge = LessonCharge::sole();
        $this->assertSame(['coaching', '60.00', $owner->id, 'Coaching (2 parcours) avec Jimmy BLANC – CSO de Lyon'], [$charge->kind, $charge->amount, $charge->payer_id, $charge->label]);
        $this->assertNotNull($charge->expense_id, 'Coaching du propriétaire : aussi dans son budget.');
        $this->actingAs($owner)->get(route('calendar.show', $event))->assertSee('data-event-coaching-cost', false)->assertSee('60,00 €');
        // Formulaire du concours : nombre de parcours coachés.
        $this->actingAs($owner)->get(route('calendar.edit', $event))->assertSee('data-event-coaching', false);
        $this->assertSame(InstructorRate::KINDS['coaching'], 'Coaching en concours');
    }
}
