<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HorseAccessGrant;
use App\Models\HorsePhoto;
use Tests\TestCase;

/**
 * Suppressions sur téléphone : glisser une dépense ou un soin pour le supprimer, avec « Annuler » ;
 * confirmations dans l'application au lieu des boîtes du navigateur ; boutons photo à taille de doigt.
 */
class DeleteWithUndoTest extends TestCase
{
    public function test_expense_swiped_away_can_be_restored(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner);
        $category = ExpenseCategory::where('key', 'farrier')->value('id');
        $this->actingAs($owner)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $category, 'spent_on' => now()->toDateString(), 'amount' => '70'])->assertRedirect();
        $expense = Expense::where('horse_id', $horse->id)->sole();

        $this->actingAs($owner)->get('/budget')->assertOk()->assertSee('data-swipe-row', false)
            ->assertSee('glissez une dépense vers la gauche pour la supprimer', false)->assertSee('Dépense de 70,00', false);

        $restoreUrl = $this->actingAs($owner)->deleteJson("/budget/{$expense->id}")->assertOk()->assertJson(['deleted' => true])->json('restore_url');
        $this->assertSoftDeleted($expense);

        $this->actingAs($owner)->postJson($restoreUrl)->assertOk()->assertJson(['restored' => true]);
        $this->assertNotSoftDeleted($expense);
        $this->actingAs($owner)->postJson($restoreUrl)->assertNotFound();

        // Ordinateur (formulaire classique) : redirection inchangée.
        $this->actingAs($owner)->delete("/budget/{$expense->id}")->assertRedirect();
        $this->assertSoftDeleted($expense);
    }

    public function test_only_people_allowed_to_edit_an_expense_can_swipe_or_restore_it(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::value('id'), 'spent_on' => now()->toDateString(), 'amount' => '12']);
        $expense = Expense::where('horse_id', $horse->id)->sole();

        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'expenses.view']]);
        $this->actingAs($viewer)->get('/budget')->assertOk()->assertSee('data-expense-link="'.$expense->id.'"', false)
            ->assertDontSee('data-swipe-row', false)->assertDontSee('data-swipe-hint', false);
        $this->actingAs($viewer)->deleteJson("/budget/{$expense->id}")->assertForbidden();

        $this->actingAs($owner)->deleteJson("/budget/{$expense->id}")->assertOk();
        $this->actingAs($viewer)->postJson("/budget/{$expense->id}/restaurer")->assertForbidden();
        $this->actingAs($this->user())->postJson("/budget/{$expense->id}/restaurer")->assertForbidden();
        $this->assertSoftDeleted($expense);
    }

    public function test_care_swiped_away_comes_back_with_its_agenda_entries(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($owner);
        $farrier = CareCategory::where('key', 'farrier')->value('id');
        $this->actingAs($owner)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => $farrier, 'performed_at' => now()->subDays(3)->format('Y-m-d').' 11:00',
            'reason' => 'Parage', 'next_check_on' => now()->addWeeks(6)->toDateString()])->assertRedirect();
        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $events = fn () => CalendarEvent::where('care_record_id', $care->id)->orWhere(fn ($q) => $q->where('source_type', $care->getMorphClass())->where('source_id', $care->id))->count();
        $before = $events();
        $this->assertGreaterThanOrEqual(1, $before);

        $this->actingAs($owner)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertSee('data-swipe-row', false)
            ->assertSee('glissez un soin vers la gauche pour le supprimer', false);

        $restoreUrl = $this->actingAs($owner)->deleteJson("/chevaux/{$horse->id}/soins/{$care->id}")->assertOk()->assertJson(['deleted' => true])->json('restore_url');
        $this->assertSoftDeleted($care);
        $this->assertSame(0, $events());

        $this->actingAs($owner)->postJson($restoreUrl)->assertOk()->assertJson(['restored' => true]);
        $this->assertNotSoftDeleted($care);
        $this->assertSame($before, $events());

        // Le soin d'un autre cheval ne se restaure pas par cette adresse.
        $other = $this->horseFor($owner);
        $this->actingAs($owner)->deleteJson("/chevaux/{$horse->id}/soins/{$care->id}")->assertOk();
        $this->actingAs($owner)->postJson("/chevaux/{$other->id}/soins/{$care->id}/restaurer")->assertNotFound();

        // Lecture seule : ni glissé ni restauration.
        $this->actingAs($owner)->postJson($restoreUrl)->assertOk();
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'health.view']]);
        $this->actingAs($viewer)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertSee('Parage')->assertDontSee('data-swipe-row', false);
        $this->actingAs($viewer)->deleteJson("/chevaux/{$horse->id}/soins/{$care->id}")->assertForbidden();
    }

    public function test_confirmations_are_shown_in_the_app_and_photo_buttons_are_finger_sized(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        foreach ([1, 2] as $i) {
            $photo = HorsePhoto::create(['horse_id' => $horse->id, 'path' => "photos/test-$i.jpg", 'uploaded_by' => $owner->id]);
        }
        $horse->update(['main_photo_path' => (string) $photo->id]);

        $page = $this->actingAs($owner)->get("/chevaux/{$horse->id}")->assertOk();
        // Plus aucune boîte native du navigateur : la fenêtre de l'application prend le relais.
        $page->assertDontSee('return confirm(', false)
            ->assertSee('data-confirm="Archiver ce cheval ?', false)
            ->assertSee('data-confirm="Supprimer cette photo ? Elle sera définitivement effacée."', false);
        // Photo principale signalée ; un seul bouton « principale » (pour l'autre photo), boutons de 44 px.
        $page->assertSee('aria-label="Choisir comme photo principale"', false)->assertSee('aria-label="Supprimer la photo"', false)
            ->assertSee('size-11', false)->assertDontSee('Suppr.</button>', false);
        $this->assertSame(1, substr_count($page->getContent(), 'aria-label="Choisir comme photo principale"'));

        foreach (['/budget', '/seances', '/parametres/securite', '/partages', "/chevaux/{$horse->id}/sante"] as $url) {
            $this->assertStringNotContainsString('confirm(', $this->actingAs($owner)->get($url)->getContent(), $url);
        }
    }
}
