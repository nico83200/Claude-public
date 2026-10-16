<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\RecurringIncome;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Demandes de la version 2.23. */
class Version223RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function owner(): array
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');

        return [$user, $this->horseFor($user, null, ['official_name' => 'JACK', 'usual_name' => 'Jack'])];
    }

    private function income(array $data): array
    {
        return collect($data)->mapWithKeys(fn ($v, $k) => ['income_'.$k => $v])->all();
    }

    public function test_a_deleted_horse_no_longer_counts_in_the_budget(): void
    {
        [$user, $jack] = $this->owner();
        $test = $this->horseFor($user, null, ['official_name' => 'MON CHEVAL TEST']);
        $cat = ExpenseCategory::firstOrFail();
        foreach ([[$jack, 300], [$test, 177.6]] as [$h, $amount]) {
            Expense::forceCreate(['organization_id' => $h->organization_id, 'author_id' => $user->id, 'horse_id' => $h->id, 'expense_category_id' => $cat->id, 'amount' => $amount, 'spent_on' => now()->toDateString(), 'paid_on' => now()->toDateString()]);
        }
        Income::forceCreate(['organization_id' => $test->organization_id, 'author_id' => $user->id, 'horse_id' => $test->id, 'category' => 'prize', 'received_on' => now()->toDateString(), 'collected_on' => now()->toDateString(), 'amount' => 50]);
        $r = $this->actingAs($user)->get('/budget')->assertOk();
        $this->assertArrayHasKey('MON CHEVAL TEST', $r->viewData('kpis')['by_horse']->all());

        $this->actingAs($user)->delete(route('horses.destroy', $test), ['confirm_name' => 'MON CHEVAL TEST'])->assertRedirect();
        $page = $this->actingAs($user)->get('/budget')->assertOk()->assertDontSee('MON CHEVAL TEST');
        $kpis = $page->viewData('kpis');
        $this->assertSame(['JACK'], $kpis['by_horse']->keys()->all());
        $this->assertSame([300.0, 0.0], [(float) $kpis['year'], (float) $kpis['income']]);
    }

    public function test_recurring_income_with_end_date_and_received_status(): void
    {
        Carbon::setTestNow('2026-10-16 10:00');
        [$user, $horse] = $this->owner();

        // Demi-pension mensuelle depuis juillet, jusqu'à fin décembre ; échéances passées déjà reçues.
        $this->actingAs($user)->post(route('budget.incomes.store'), $this->income(['horse_id' => $horse->id, 'category' => 'half_lease', 'received_on' => '2026-07-05', 'amount' => '150',
            'recurring' => '1', 'label' => 'Demi-pension de Léa', 'frequency' => 'monthly', 'ends_on' => '2026-12-31', 'received' => '1']))->assertSessionHas('success');
        $rec = RecurringIncome::sole();
        $this->assertSame(['2026-07-05', '2026-08-05', '2026-09-05', '2026-10-05'], Income::orderBy('received_on')->pluck('received_on')->map->toDateString()->all());
        $this->assertSame(0, Income::whereNull('collected_on')->count());
        $this->assertSame('2026-11-05', $rec->next_due_on->toDateString());
        $this->actingAs($user)->get('/budget')->assertOk()->assertSee('Recettes récurrentes')->assertSee('Demi-pension de Léa')->assertDontSee('data-to-receive', false);

        // Échéance suivante : à recevoir, puis « Reçue ».
        Carbon::setTestNow('2026-11-05 08:00');
        $this->artisan('expenses:recurring')->assertSuccessful();
        $november = Income::whereNull('collected_on')->sole();
        $this->assertSame('2026-11-05', $november->received_on->toDateString());
        $this->actingAs($user)->get('/budget')->assertOk()->assertSee('data-receivable="'.$november->id.'"', false);
        $this->actingAs($user)->post(route('budget.incomes.receive', $november), ['received' => '1'])->assertSessionHas('success', 'Recette notée comme reçue.');
        $this->assertSame('2026-11-05', $november->fresh()->collected_on->toDateString());
        // Erreur : remise « à recevoir » depuis la modification.
        $this->actingAs($user)->put(route('budget.incomes.update', $november), $this->income(['horse_id' => $horse->id, 'category' => 'half_lease', 'received_on' => '2026-11-05', 'amount' => '150', 'received' => '0']))->assertSessionHas('success');
        $this->assertNull($november->fresh()->collected_on);

        // Fin de la récurrence : plus d'échéance après décembre ; récurrence terminée masquée.
        Carbon::setTestNow('2027-02-10 08:00');
        $this->artisan('expenses:recurring')->assertSuccessful();
        $this->assertSame('2026-12-05', Income::max('received_on'));
        $this->assertTrue($rec->fresh()->isEnded());
        $this->actingAs($user)->get('/budget?year=2026')->assertOk()->assertSee('Voir les recettes récurrentes terminées (1)');

        // Recette simple non reçue : « À recevoir ».
        $this->actingAs($user)->post(route('budget.incomes.store'), $this->income(['horse_id' => $horse->id, 'category' => 'prize', 'received_on' => '2027-02-10', 'amount' => '90', 'received' => '0']))
            ->assertSessionHas('success', 'Recette de 90,00 € enregistrée, à recevoir.');
        $this->assertNull(Income::where('amount', 90)->value('collected_on'));
    }

    public function test_budget_charts_include_incomes(): void
    {
        Carbon::setTestNow('2026-10-16 10:00');
        [$user, $horse] = $this->owner();
        $cat = ExpenseCategory::firstOrFail();
        Expense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $user->id, 'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => 400, 'spent_on' => '2026-03-02', 'paid_on' => '2026-03-02']);
        Income::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $user->id, 'horse_id' => $horse->id, 'category' => 'half_lease', 'received_on' => '2026-03-10', 'collected_on' => '2026-03-10', 'amount' => 150]);

        $page = $this->actingAs($user)->get('/budget')->assertOk()->assertSee('data-income-categories', false)->assertSee('Dépenses 400,00 € · recettes + 150,00 €');
        $kpis = $page->viewData('kpis');
        $this->assertSame(['Demi-pension' => 150.0], $kpis['income_by_category']->all());
        $this->assertSame(['expense' => 400.0, 'income' => 150.0, 'net' => 250.0], $kpis['by_horse']['JACK']);
        $this->assertSame([400.0, 150.0], [$kpis['series'][2]['value'], $kpis['series'][2]['income']]);

        // Filtre de catégorie de dépense : recettes retirées des graphiques.
        $filtered = $this->actingAs($user)->get('/budget?category='.$cat->id)->viewData('kpis');
        $this->assertTrue($filtered['income_by_category']->isEmpty());
    }

    public function test_health_menu_page_groups_health_feeding_follow_up_and_weight(): void
    {
        [$user, $jack] = $this->owner();
        $uni = $this->horseFor($user, null, ['official_name' => 'UNI']);

        $this->actingAs($user)->get('/sante')->assertOk()->assertSee('data-health-tabs', false)
            ->assertSee(route('health.index', ['onglet' => 'alimentation']), false)->assertDontSee('data-health-horses', false);

        // Alimentation : rations du premier cheval, bouton et formulaire ; choix du cheval.
        $page = $this->actingAs($user)->get(route('health.index', ['onglet' => 'alimentation']))->assertOk()
            ->assertSee('Santé et soins')->assertSee('data-new-ration', false)->assertSee('data-health-horses', false)
            ->assertSee('aria-current="page"', false)->assertDontSee('aria-label="Sections du dossier"', false);
        $this->assertSame($jack->id, $page->viewData('horse')->id);
        $this->assertSame($uni->id, $this->actingAs($user)->get(route('health.index', ['onglet' => 'alimentation', 'cheval' => $uni->id]))->viewData('horse')->id);

        // Ajout d'une ration depuis l'onglet : retour sur l'onglet.
        $hub = route('health.index', ['onglet' => 'alimentation', 'cheval' => $uni->id]);
        $this->actingAs($user)->from($hub)->post(route('horses.feeding.store', $uni), ['name' => 'Ration du soir', 'starts_on' => now()->toDateString(), 'end_mode' => 'none', 'entries' => [['feed' => 'Foin']]])
            ->assertRedirect($hub);
        $this->actingAs($user)->get($hub)->assertSee('Ration du soir');

        // Suivi et poids : formulaires de saisie présents, enregistrement depuis l'onglet.
        $this->actingAs($user)->get(route('health.index', ['onglet' => 'suivi', 'cheval' => $jack->id]))->assertOk()->assertSee('Nouvelle saisie')->assertSee(route('horses.logs.store', $jack), false);
        $this->actingAs($user)->get(route('health.index', ['onglet' => 'poids', 'cheval' => $jack->id]))->assertOk()->assertSee(route('horses.weight.store', $jack), false);
        $suivi = route('health.index', ['onglet' => 'suivi', 'cheval' => $jack->id]);
        $this->actingAs($user)->from($suivi)->post(route('horses.logs.store', $jack), ['appetite' => 'good', 'general_state' => 'good', 'observations' => 'RAS'])->assertRedirect($suivi);

        // Cheval d'un autre compte : jamais affiché, premier cheval accessible à la place.
        $other = $this->horseFor($this->user());
        $this->assertSame($jack->id, $this->actingAs($user)->get(route('health.index', ['onglet' => 'poids', 'cheval' => $other->id]))->viewData('horse')->id);

        // Aucun cheval : onglet vide expliqué.
        $this->actingAs($this->user())->get(route('health.index', ['onglet' => 'alimentation']))->assertOk()->assertSee('Aucun cheval à afficher');
    }
}
