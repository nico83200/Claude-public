<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Notifications\AppNotification;
use App\Services\RecurringExpenses;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecurringExpensesTest extends TestCase
{
    private function setUpOwner(): array
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization());
        $horse = $this->horseFor($user, null, ['usual_name' => 'Uni']);
        $cat = ExpenseCategory::where('name', 'Pension')->first() ?? ExpenseCategory::firstOrFail();

        return [$user, $horse, $cat];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_monthly_board_creates_due_payments_without_duplicates_and_tracks_payment(): void
    {
        Carbon::setTestNow('2026-03-15 10:00');
        [$user, $horse, $cat] = $this->setUpOwner();

        $this->actingAs($user)->post('/budget', [
            'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '380', 'spent_on' => '2026-01-31',
            'recurring' => '1', 'label' => 'Pension', 'frequency' => 'monthly', 'paid' => '0',
        ])->assertSessionHas('success');

        $rec = RecurringExpense::firstOrFail();
        // 31/01, 28/02 (mois court), puis 31/03 pas encore arrivée
        $this->assertSame(['2026-01-31', '2026-02-28'], Expense::orderBy('spent_on')->pluck('spent_on')->map->toDateString()->all());
        $this->assertSame('2026-03-31', $rec->next_due_on->toDateString());
        $this->assertTrue(Expense::whereNull('paid_on')->count() === 2);

        // Pas de doublon, pas de dérive : 31/03 puis 30/04 puis 31/05
        Carbon::setTestNow('2026-05-31 08:00');
        $this->artisan('expenses:recurring')->assertSuccessful();
        $this->artisan('expenses:recurring')->assertSuccessful();
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'], Expense::orderBy('spent_on')->pluck('spent_on')->map->toDateString()->all());

        // Suivi du paiement
        $first = Expense::orderBy('spent_on')->first();
        $this->actingAs($user)->get('/budget')->assertOk()->assertSee('À payer')->assertSee('Dépenses récurrentes')->assertSee('Pension');
        $this->actingAs($user)->post(route('budget.pay', $first), ['paid' => '1', 'paid_on' => '2026-02-02'])->assertSessionHas('success');
        $this->assertSame('2026-02-02', $first->fresh()->paid_on->toDateString());
        $this->actingAs($user)->post(route('budget.pay', $first), ['paid' => '0'])->assertSessionHas('success');
        $this->assertNull($first->fresh()->paid_on);
        $this->actingAs($user)->post(route('budget.pay', $first), ['paid' => '1', 'paid_on' => '2027-01-01'])->assertSessionHasErrors('paid_on');
    }

    public function test_already_paid_marks_the_past_occurrences_paid_but_not_the_next_ones(): void
    {
        Carbon::setTestNow('2026-03-15 10:00');
        [$user, $horse, $cat] = $this->setUpOwner();

        $this->actingAs($user)->post('/budget', [
            'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '380', 'spent_on' => '2026-01-10',
            'recurring' => '1', 'label' => 'Pension', 'frequency' => 'monthly', 'paid' => '1',
        ])->assertSessionHas('success');

        // 10/01, 10/02, 10/03 : passées, donc réglées.
        $this->assertSame(3, Expense::count());
        $this->assertSame(0, Expense::whereNull('paid_on')->count());
        $this->actingAs($user)->get('/budget')->assertOk()->assertDontSee('id="to-pay"', false);

        // L'échéance suivante, générée plus tard, reste à payer.
        Carbon::setTestNow('2026-04-10 08:00');
        app(RecurringExpenses::class)->generate(RecurringExpense::firstOrFail(), notify: false);
        $this->assertSame(['2026-04-10'], Expense::whereNull('paid_on')->pluck('spent_on')->map->toDateString()->all());

        // Échéance du jour même à la création : réglée aussi, la date d'aujourd'hui n'étant pas à venir.
        Carbon::setTestNow('2026-05-20 09:00');
        $this->actingAs($user)->post('/budget', [
            'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '40', 'spent_on' => '2026-05-20',
            'recurring' => '1', 'label' => 'Assurance', 'frequency' => 'monthly', 'paid' => '1',
        ])->assertSessionHas('success');
        $this->assertNotNull(Expense::where('amount', 40)->value('paid_on'));

        // Toutes les semaines depuis plus d'un an (au-delà de 36 échéances) : toutes réglées, aucune ne revient « à payer ».
        $this->actingAs($user)->post('/budget', [
            'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '15', 'spent_on' => '2025-04-01',
            'recurring' => '1', 'label' => 'Paddock', 'frequency' => 'weekly', 'paid' => '1',
        ])->assertSessionHas('success');
        $this->actingAs($user)->get('/budget')->assertOk();
        $weekly = Expense::where('amount', 15);
        $this->assertGreaterThan(36, (clone $weekly)->count());
        $this->assertSame(0, (clone $weekly)->whereNull('paid_on')->count());
    }

    public function test_due_day_notification_pause_resume_and_stop(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-06-01 06:05');
        [$user, $horse, $cat] = $this->setUpOwner();
        $this->actingAs($user)->post('/budget', [
            'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '120', 'spent_on' => '2026-06-08',
            'recurring' => '1', 'label' => 'Assurance', 'frequency' => 'weekly', 'paid' => '0',
        ]);
        $rec = RecurringExpense::firstOrFail();
        $this->assertSame(0, Expense::count());

        Carbon::setTestNow('2026-06-08 06:05');
        $this->artisan('expenses:recurring');
        Notification::assertSentTo($user, AppNotification::class, fn ($n) => $n->kind === 'payment_due' && str_contains($n->body, 'Assurance – Uni : 120,00 €'));

        // Suspendue : rien n'est créé ; reprise sans arriéré
        $this->actingAs($user)->post(route('budget.recurring.toggle', $rec))->assertSessionHas('success');
        Carbon::setTestNow('2026-06-29 06:05');
        $this->artisan('expenses:recurring');
        $this->assertSame(1, Expense::count());
        $this->actingAs($user)->post(route('budget.recurring.toggle', $rec));
        // Reprise le 29/06 : l'échéance du jour est créée, celles du 15 et du 22 (suspension) ne le sont pas.
        $this->assertSame(['2026-06-08', '2026-06-29'], Expense::orderBy('spent_on')->pluck('spent_on')->map->toDateString()->all());
        $this->assertSame('2026-07-06', $rec->fresh()->next_due_on->toDateString());

        // Arrêt : les échéances passées restent
        $this->actingAs($user)->delete(route('budget.recurring.destroy', $rec))->assertSessionHas('success');
        $this->assertSoftDeleted($rec);
        $this->assertSame(2, Expense::count());
    }

    public function test_simple_expense_is_paid_by_default_or_left_to_pay(): void
    {
        [$user, $horse, $cat] = $this->setUpOwner();
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '50', 'spent_on' => now()->toDateString()]);
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '70', 'spent_on' => now()->toDateString(), 'paid' => '0']);
        $this->assertNotNull(Expense::where('amount', 50)->value('paid_on'));
        $this->assertNull(Expense::where('amount', 70)->value('paid_on'));
    }

    public function test_others_cannot_see_or_manage_a_recurring_expense(): void
    {
        [$user, $horse, $cat] = $this->setUpOwner();
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '380', 'spent_on' => now()->toDateString(), 'recurring' => '1', 'label' => 'Pension SECRÈTE', 'frequency' => 'monthly']);
        $rec = RecurringExpense::firstOrFail();
        $other = $this->user();
        $this->actingAs($other)->get('/budget')->assertOk()->assertDontSee('Pension SECRÈTE');
        $this->actingAs($other)->post(route('budget.recurring.toggle', $rec))->assertForbidden();
        $this->actingAs($other)->delete(route('budget.recurring.destroy', $rec))->assertForbidden();
        $this->actingAs($other)->post(route('budget.pay', $rec->occurrences()->first()), ['paid' => '0'])->assertForbidden();
        $this->actingAs($other)->put(route('budget.recurring.update', $rec), ['label' => 'x', 'amount' => 1, 'frequency' => 'monthly', 'next_due_on' => now()->toDateString()])->assertForbidden();
    }
}
