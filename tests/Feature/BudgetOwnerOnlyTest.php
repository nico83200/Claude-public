<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HorseAccessGrant;
use App\Models\HorseOrganizationAssignment;
use App\Models\HorseOwnership;
use App\Models\Income;
use App\Models\Invitation;
use App\Services\HorseAccess;
use App\Support\Perm;
use Tests\TestCase;

/** 2.42.1 : le budget du cheval (dépenses, recettes) est réservé au propriétaire ; aucun partage ni écurie de rattachement n'y accède. */
class BudgetOwnerOnlyTest extends TestCase
{
    private function setUpHorse(): array
    {
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $expense = Expense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $owner->id, 'horse_id' => $horse->id,
            'expense_category_id' => ExpenseCategory::where('key', 'boarding')->value('id'), 'amount' => 390, 'spent_on' => now()->toDateString(), 'supplier' => 'Écurie des Pins']);
        Income::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $owner->id, 'horse_id' => $horse->id, 'category' => 'half_lease', 'received_on' => now()->toDateString(), 'amount' => 150, 'payer' => 'Léa']);

        return [$owner, $horse, $expense];
    }

    public function test_a_half_lease_never_sees_the_budget_even_with_old_rights(): void
    {
        [$owner, $horse, $expense] = $this->setUpHorse();
        $lea = $this->user(['name' => 'Léa DURAND']);
        // Partage enregistré avant la 2.42.1 avec les droits budget cochés.
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $lea->id, 'label' => 'Demi-pension', 'permissions' => [...Perm::halfLeasePreset(), Perm::EXPENSES_VIEW, Perm::EXPENSES_CREATE]]);

        $access = app(HorseAccess::class);
        $this->assertNotContains(Perm::EXPENSES_VIEW, $access->permissions($lea, $horse));
        $this->assertContains(Perm::SESSIONS_CREATE, $access->permissions($lea, $horse), 'Le reste du partage est inchangé.');

        $this->actingAs($lea)->get(route('budget.index'))->assertOk()->assertDontSee('Écurie des Pins')->assertDontSee('data-expense-link="'.$expense->id.'"', false);
        $this->actingAs($lea)->get(route('budget.edit', $expense))->assertForbidden();
        $this->actingAs($lea)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::value('id'), 'spent_on' => now()->toDateString(), 'amount' => '20'])->assertForbidden();
        $this->actingAs($lea)->get(route('horses.show', $horse))->assertOk()->assertDontSee(route('budget.index', ['horse' => $horse->id]), false);

        // Le propriétaire, lui, voit tout ; un copropriétaire déclaré aussi.
        $this->actingAs($owner)->get(route('budget.index'))->assertOk()->assertSee('Écurie des Pins');
        $partner = $this->user();
        HorseOwnership::create(['horse_id' => $horse->id, 'user_id' => $partner->id, 'role' => 'co_owner']);
        app()->forgetInstance(HorseAccess::class);
        $this->assertContains(Perm::EXPENSES_VIEW, app(HorseAccess::class)->permissions($partner, $horse));
    }

    public function test_share_form_and_stable_assignment_no_longer_offer_budget_rights(): void
    {
        [$owner, $horse] = $this->setUpHorse();
        $page = $this->actingAs($owner)->get(route('horses.sharing', $horse))->assertOk()->assertSee('data-perm-budget-note', false);
        $this->assertStringNotContainsString('value="expenses.view"', $page->getContent());

        // Invitation envoyée avec les droits budget (ancien formulaire) : ils sont retirés.
        $this->actingAs($owner)->post(route('horses.sharing.invite', $horse), ['email' => 'lea@example.test', 'label' => 'Demi-pension', 'permissions' => ['sessions.view', 'expenses.view', 'expenses.create']])
            ->assertSessionHas('success');
        $this->assertSame(['horse.view', 'sessions.view'], Invitation::where('email', 'lea@example.test')->sole()->permissions);

        // Droits d'un partage modifiés : idem.
        $rider = $this->user();
        $grant = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW]]);
        $this->actingAs($owner)->put(route('horses.sharing.grants.update', [$horse, $grant]), ['permissions' => ['sessions.view', 'expenses.view']])->assertSessionHas('success');
        $this->assertSame(['horse.view', 'sessions.view'], $grant->fresh()->permissions);

        // Écurie de rattachement : jamais de budget, même si les droits étaient enregistrés.
        $manager = $this->user();
        $stable = $this->stable($manager, 'Écurie des Pins');
        HorseOrganizationAssignment::forceCreate(['horse_id' => $horse->id, 'organization_id' => $stable->id, 'kind' => 'boarding', 'status' => 'active', 'starts_at' => now()->subDay(),
            'permissions' => [Perm::HORSE_VIEW, Perm::HEALTH_VIEW, Perm::EXPENSES_VIEW, Perm::EXPENSES_CREATE]]);
        app()->forgetInstance(HorseAccess::class);
        $perms = app(HorseAccess::class)->permissions($manager, $horse);
        $this->assertContains(Perm::HEALTH_VIEW, $perms);
        $this->assertNotContains(Perm::EXPENSES_VIEW, $perms);
    }

    public function test_migration_removes_budget_rights_already_saved(): void
    {
        [$owner, $horse] = $this->setUpHorse();
        $lea = $this->user();
        $grant = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $lea->id, 'permissions' => [Perm::HORSE_VIEW, Perm::EXPENSES_VIEW, Perm::SESSIONS_VIEW, Perm::EXPENSES_CREATE]]);
        $stable = $this->stable($this->user(), 'Écurie du Lac');
        $assignment = HorseOrganizationAssignment::forceCreate(['horse_id' => $horse->id, 'organization_id' => $stable->id, 'kind' => 'care', 'status' => 'active', 'permissions' => [Perm::HORSE_VIEW, Perm::EXPENSES_VIEW]]);
        $untouched = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $this->user()->id, 'permissions' => [Perm::HORSE_VIEW, Perm::HEALTH_VIEW]]);

        (require database_path('migrations/2026_01_01_005500_budget_owner_only.php'))->up();

        $this->assertSame([Perm::HORSE_VIEW, Perm::SESSIONS_VIEW], $grant->fresh()->permissions);
        $this->assertSame([Perm::HORSE_VIEW], $assignment->fresh()->permissions);
        $this->assertSame([Perm::HORSE_VIEW, Perm::HEALTH_VIEW], $untouched->fresh()->permissions);
        $this->assertSame(1, Expense::where('horse_id', $horse->id)->count(), 'Les dépenses déjà saisies restent.');
    }
}
