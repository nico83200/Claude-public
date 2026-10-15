<?php

namespace Tests\Feature;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HorseAccessGrant;
use App\Models\Professional;
use App\Services\HorseAccess;
use App\Services\ProfessionalDirectory;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Intervenants des chevaux partagés, Paramètres regroupés, détail des dépenses, graphiques agrandis. */
class SharedProfessionalsAndExpenseDetailTest extends TestCase
{
    public function test_professionals_of_shared_horses_are_visible_read_only(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $vet = Professional::forceCreate(['organization_id' => $horse->organization_id, 'last_name' => 'Vétodoc', 'first_name' => 'Anne', 'kind' => 'veterinarian', 'phone' => '0601020304', 'notes' => 'Note privée du propriétaire', 'usual_rate' => 80]);
        $farrier = Professional::forceCreate(['organization_id' => $horse->organization_id, 'last_name' => 'Ferrant', 'kind' => 'farrier']);
        $other = Professional::forceCreate(['organization_id' => $horse->organization_id, 'last_name' => 'Inconnu', 'kind' => 'other']);
        $horse->professionals()->attach($vet->id);
        CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => CareCategory::value('id'), 'performed_at' => now()->subDay(), 'professional_id' => $farrier->id, 'author_id' => $owner->id]);

        $rider = $this->user();
        $grant = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view']]);

        $page = $this->actingAs($rider)->get('/intervenants')->assertOk()->assertSee('Intervenants des chevaux partagés avec vous');
        $page->assertSee('data-shared-professional="'.$vet->id.'"', false)->assertDontSee('data-shared-professional="'.$other->id.'"', false);
        // Le maréchal n'apparaît que via le carnet de santé : il faut l'accès santé.
        $page->assertDontSee('data-shared-professional="'.$farrier->id.'"', false);
        $this->actingAs($rider)->get(route('professionals.show', $vet))->assertOk()->assertSee('0601020304')->assertSee('data-shared-notice', false)
            ->assertDontSee('Note privée du propriétaire')->assertDontSee('Modifier');
        $this->actingAs($rider)->get(route('professionals.show', $other))->assertNotFound();
        $this->actingAs($rider)->get(route('professionals.edit', $vet))->assertForbidden();

        $grant->update(['permissions' => ['horse.view', 'health.view']]);
        app()->forgetInstance(ProfessionalDirectory::class);
        app(HorseAccess::class)->flush();
        $this->actingAs($rider)->get('/intervenants')->assertSee('data-shared-professional="'.$farrier->id.'"', false);
        // Utilisable dans l'agenda pour ce cheval.
        $this->actingAs($rider)->get(route('calendar.index'))->assertOk();

        // Partage retiré : plus visible.
        $grant->update(['revoked_at' => now()]);
        app()->forgetInstance(ProfessionalDirectory::class);
        app(HorseAccess::class)->flush();
        $this->actingAs($rider)->get(route('professionals.show', $vet))->assertNotFound();
        $this->actingAs($this->user())->get(route('professionals.show', $vet))->assertNotFound();
    }

    public function test_assistant_and_organisation_live_in_settings(): void
    {
        $user = $this->user();
        foreach (['/parametres', '/assistant', '/organisation'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('href="'.route('assistant.history').'"', $html);
            $this->assertStringContainsString('href="'.route('organization.show').'"', $html);
        }
        $nav = $this->actingAs($user)->get('/dashboard')->getContent();
        preg_match('#<aside.*?</aside>#s', $nav, $m);
        $this->assertStringNotContainsString(route('organization.show').'"', $m[0]);
        $this->assertStringNotContainsString(route('assistant.history').'"', $m[0]);
        $this->assertStringContainsString(route('settings.profile'), $m[0]);
    }

    public function test_expense_detail_or_linked_care_with_finance(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);
        $board = ExpenseCategory::where('key', '!=', 'farrier')->whereNotIn('key', ['veterinary', 'osteopathy', 'dentistry'])->value('id');
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $board, 'spent_on' => now()->toDateString(), 'amount' => '450', 'supplier' => 'Écurie du Lac', 'comment' => 'Pension octobre']);
        $simple = Expense::where('amount', 450)->sole();

        $budget = $this->actingAs($user)->get('/budget')->assertOk()->assertSee('data-expense-link="'.$simple->id.'"', false);
        foreach (['category', 'horse', 'month'] as $chart) {
            $budget->assertSee('data-chart-dialog="'.$chart.'"', false)->assertSee('data-chart-open="'.$chart.'"', false);
        }
        $this->actingAs($user)->get(route('budget.show', $simple))->assertOk()->assertSee('data-expense-detail', false)
            ->assertSee('450,00 €')->assertSee('Écurie du Lac')->assertSee('Pension octobre')->assertSee('Payée');

        $farrier = ExpenseCategory::where('key', 'farrier')->value('id');
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $farrier, 'spent_on' => now()->subDay()->toDateString(), 'amount' => '70', 'supplier' => 'M. Durand']);
        $careExpense = Expense::where('amount', 70)->sole();
        $this->actingAs($user)->get(route('budget.show', $careExpense))->assertRedirect(route('horses.care.show', [$horse, $careExpense->care_record_id]));
        $this->actingAs($user)->get(route('horses.care.show', [$horse, $careExpense->care_record_id]))->assertOk()
            ->assertSee('Informations financières')->assertSee('70,00 €')->assertSee('M. Durand')->assertSee('Voir dans le budget');

        $this->actingAs($this->user())->get(route('budget.show', $simple))->assertNotFound();

        // Suppression depuis le détail : retour au budget.
        $this->actingAs($user)->from(route('budget.show', $simple))->delete(route('budget.destroy', $simple))->assertRedirect(route('budget.index', ['horse' => $horse->id]));
    }

    public function test_expense_can_be_edited_from_its_detail_and_from_the_care(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);
        $board = ExpenseCategory::where('key', 'boarding')->value('id');
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $board, 'spent_on' => now()->toDateString(), 'amount' => '450']);
        $e = Expense::sole();
        $this->actingAs($user)->get(route('budget.show', $e))->assertSee('data-expense-edit', false)->assertSee('data-expense-edit-bottom', false);
        $this->actingAs($user)->get(route('budget.edit', ['expense' => $e, 'retour' => '/budget/'.$e->id]))->assertOk()->assertSee('name="return_to" value="/budget/'.$e->id.'"', false);
        $this->actingAs($user)->put(route('budget.update', $e), ['horse_id' => $horse->id, 'expense_category_id' => $board, 'spent_on' => now()->toDateString(), 'amount' => '470', 'return_to' => '/budget/'.$e->id])
            ->assertRedirect('/budget/'.$e->id);
        $this->assertSame('470.00', $e->fresh()->amount);
        // Jamais de redirection vers un autre site.
        $this->actingAs($user)->put(route('budget.update', $e), ['horse_id' => $horse->id, 'expense_category_id' => $board, 'spent_on' => now()->toDateString(), 'amount' => '470', 'return_to' => '//evil.example'])
            ->assertRedirect(route('budget.index'));

        $farrier = ExpenseCategory::where('key', 'farrier')->value('id');
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $farrier, 'spent_on' => now()->subDay()->toDateString(), 'amount' => '70']);
        $care = Expense::where('amount', 70)->sole();
        $this->actingAs($user)->get(route('horses.care.show', [$horse, $care->care_record_id]))->assertSee('data-care-expense-edit', false);
    }

    public function test_maintenance_page_is_friendly(): void
    {
        Artisan::call('down', ['--secret' => 'x']);
        try {
            $this->get('/')->assertStatus(503)->assertSee('Jack&amp;Cie se refait une beauté', false)->assertSee('data-maintenance', false)->assertSee('/maintenance/reprise', false)->assertDontSee('Service Unavailable');
        } finally {
            Artisan::call('up');
        }
    }
}
