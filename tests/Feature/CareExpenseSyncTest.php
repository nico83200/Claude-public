<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RidingSession;
use Tests\TestCase;

/** Soin ↔ dépense synchronisés, soin créé depuis une dépense, saisies passées marquées terminées, réglages par défaut du formulaire. */
class CareExpenseSyncTest extends TestCase
{
    private function setUpOwner(): array
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');

        return [$user, $this->horseFor($user)];
    }

    public function test_editing_a_care_updates_its_expense_and_back(): void
    {
        [$user, $horse] = $this->setUpOwner();
        $vet = CareCategory::where('key', 'veterinary')->value('id');
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => $vet, 'performed_at' => now()->subDays(3)->format('Y-m-d').' 10:00', 'reason' => 'Boiterie', 'cost' => '80', 'add_expense' => '1', 'next_mode' => 'none'])->assertRedirect();
        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $expense = Expense::where('care_record_id', $care->id)->sole();
        $this->assertSame(['80.00', now()->subDays(3)->toDateString()], [$expense->amount, $expense->spent_on->toDateString()]);

        // Soin modifié (date et tarif) : la dépense suit.
        $this->actingAs($user)->put("/chevaux/{$horse->id}/soins/{$care->id}", ['care_category_id' => $vet, 'performed_at' => now()->subDays(5)->format('Y-m-d').' 14:00', 'reason' => 'Boiterie', 'cost' => '95.50', 'next_mode' => 'none', 'version' => $care->version])->assertRedirect();
        $this->assertSame(['95.50', now()->subDays(5)->toDateString()], [$expense->fresh()->amount, $expense->fresh()->spent_on->toDateString()]);
        $this->assertSame(1, Expense::where('care_record_id', $care->id)->count(), 'Pas de seconde dépense.');
        $this->actingAs($user)->get("/chevaux/{$horse->id}/soins/{$care->id}/modifier")->assertSee('data-linked-expense', false);

        // Dépense modifiée : le soin suit (l'heure est gardée).
        $this->actingAs($user)->put("/budget/{$expense->id}", ['horse_id' => $horse->id, 'expense_category_id' => $expense->expense_category_id, 'spent_on' => now()->subDays(4)->toDateString(), 'amount' => '110'])->assertRedirect();
        $care->refresh();
        $this->assertSame(['110.00', now()->subDays(4)->format('Y-m-d').' 14:00'], [$care->cost, $care->performed_at->format('Y-m-d H:i')]);
        // L'agenda suit le soin.
        $this->assertSame(now()->subDays(4)->toDateString(), CalendarEvent::where('care_record_id', $care->id)->sole()->starts_at->toDateString());
    }

    public function test_a_care_expense_creates_the_care_with_its_details(): void
    {
        [$user, $horse] = $this->setUpOwner();
        $farrier = ExpenseCategory::where('key', 'farrier')->value('id');
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $farrier, 'spent_on' => now()->subDays(2)->toDateString(), 'amount' => '70', 'supplier' => 'M. Durand', 'comment' => 'Parage 4 pieds'])
            ->assertSessionHas('success', 'Dépense enregistrée, et soin ajouté au carnet de santé.');
        $expense = Expense::where('horse_id', $horse->id)->sole();
        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $this->assertSame($care->id, $expense->care_record_id);
        $this->assertSame(['farrier', '70.00', 'Parage 4 pieds', now()->subDays(2)->toDateString()], [$care->category->key, $care->cost, $care->reason, $care->performed_at->toDateString()]);
        $this->assertSame('done', CalendarEvent::where('care_record_id', $care->id)->sole()->status);

        // Case décochée, ou catégorie sans soin : pas de soin.
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $farrier, 'spent_on' => now()->toDateString(), 'amount' => '20', 'add_care' => '0']);
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::where('key', 'feed')->value('id'), 'spent_on' => now()->toDateString(), 'amount' => '35']);
        $this->assertSame(1, CareRecord::where('horse_id', $horse->id)->count());
        $this->actingAs($user)->get('/budget')->assertSee('data-add-care', false);
    }

    public function test_past_sessions_are_completed_and_form_defaults(): void
    {
        [$user, $horse] = $this->setUpOwner();
        $this->actingAs($user)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->subDay()->format('Y-m-d').' 10:00', 'session_type' => 'free', 'planned_minutes' => 45])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'terminée'));
        $past = RidingSession::where('horse_id', $horse->id)->sole();
        $this->assertSame('completed', $past->status);
        $this->assertSame('done', CalendarEvent::where('source_id', $past->id)->where('source_type', $past->getMorphClass())->sole()->status);
        $this->actingAs($user)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->addDay()->format('Y-m-d').' 10:00', 'session_type' => 'free', 'planned_minutes' => 45]);
        $this->assertSame('planned', RidingSession::where('horse_id', $horse->id)->where('scheduled_at', '>', now())->sole()->status);

        // Nouveau soin : « Dans un délai » et dépense cochée par défaut.
        $html = $this->actingAs($user)->get("/chevaux/{$horse->id}/soins/nouveau")->assertOk()->getContent();
        $this->assertStringContainsString("mode: 'interval'", $html);
        $this->assertStringContainsString('data-add-expense', $html);
        // Soin dont le contrôle suit la fréquence du rappel : « Dans un délai » aussi en modification.
        $farrier = CareCategory::where('key', 'farrier')->value('id');
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => $farrier, 'performed_at' => now()->subDay()->format('Y-m-d').' 10:00', 'next_mode' => 'interval', 'next_interval_value' => 6, 'next_interval_unit' => 'weeks', 'remember_frequency' => '1']);
        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $this->assertStringContainsString("mode: 'interval'", $this->actingAs($user)->get("/chevaux/{$horse->id}/soins/{$care->id}/modifier")->getContent());
    }
}
