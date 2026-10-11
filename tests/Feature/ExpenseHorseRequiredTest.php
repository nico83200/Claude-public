<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Tests\TestCase;

/** Toute dépense se rapporte à un cheval. */
class ExpenseHorseRequiredTest extends TestCase
{
    public function test_expense_without_horse_is_refused(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);
        $cat = ExpenseCategory::where('key', 'feed')->value('id');

        $this->actingAs($user)->post('/budget', ['expense_category_id' => $cat, 'spent_on' => now()->toDateString(), 'amount' => '20'])->assertSessionHasErrors('horse_id');
        $this->assertSame(0, Expense::count());
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat, 'spent_on' => now()->toDateString(), 'amount' => '20'])->assertSessionHas('success');

        $e = Expense::first();
        $this->actingAs($user)->put("/budget/{$e->id}", ['expense_category_id' => $cat, 'spent_on' => now()->toDateString(), 'amount' => '25'])->assertSessionHasErrors('horse_id');
        $this->actingAs($user)->get('/budget')->assertDontSee('Dépense générale de l');
    }
}
