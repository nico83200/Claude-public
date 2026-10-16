<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HealthObservation;
use App\Models\HorseAccessGrant;
use App\Models\Professional;
use App\Models\Treatment;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Plusieurs chevaux (lot F) : à prévoir ensemble, passage rapide, filtres d'écurie. */
class Version230RequestsTest extends TestCase
{
    private function owner(int $count = 3): array
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        $horses = collect(range(1, $count))->map(fn ($i) => $this->horseFor($user, null, ['official_name' => 'CHEVAL '.$i, 'usual_name' => 'Cheval '.$i]));

        return [$user, $horses];
    }

    public function test_same_care_due_for_several_horses_is_planned_together(): void
    {
        [$user, $horses] = $this->owner();
        $this->allHorses($user);
        $farrier = CareCategory::where('key', 'farrier')->firstOrFail();
        $pro = Professional::forceCreate(['organization_id' => $horses[0]->organization_id, 'first_name' => 'Julien', 'last_name' => 'Moreau', 'kind' => 'farrier']);
        $cares = $horses->take(2)->map(fn ($h, $i) => CareRecord::forceCreate(['horse_id' => $h->id, 'care_category_id' => $farrier->id, 'professional_id' => $pro->id, 'author_id' => $user->id,
            'performed_at' => now()->subWeeks(6), 'next_check_on' => now()->addDays(5 + $i)->toDateString()]));
        // Une seule échéance de vaccin : pas de regroupement proposé.
        CareRecord::forceCreate(['horse_id' => $horses[2]->id, 'care_category_id' => CareCategory::where('key', 'vaccination')->value('id'), 'author_id' => $user->id, 'performed_at' => now()->subYear(), 'next_check_on' => now()->addDays(3)->toDateString()]);

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-to-plan', false)
            ->assertSee('data-to-plan-group="'.$farrier->id.'"', false)->assertSee('Habituellement : Julien Moreau')
            ->assertSee('name="with[]" value="'.$cares[1]->id.'" checked', false);
        $this->assertCount(1, $page->viewData('toPlan'));

        $day = now()->addDays(6);
        $this->actingAs($user)->post(route('horses.care.check.plan', [$horses[0], $cares[0]]), ['date' => $day->toDateString(), 'time' => '09:30', 'with' => [$cares[1]->id]])->assertSessionHas('success');
        $events = CalendarEvent::whereIn('source_id', $cares->pluck('id'))->get();
        $this->assertCount(2, $events);
        $this->assertSame(1, $events->pluck('group_uuid')->filter()->unique()->count());
        // Prévu : le groupe disparaît de « À prévoir ensemble ».
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('data-to-plan-group="'.$farrier->id.'"', false);
    }

    public function test_quick_switch_chips_on_a_horse_dashboard(): void
    {
        [$user, $horses] = $this->owner();
        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard-solo')->assertSee('data-horse-chips', false);
        foreach ($horses as $h) {
            $page->assertSee('data-horse-chip="'.$h->id.'"', false);
        }
        $this->actingAs($user)->post(route('focus.set'), ['horse' => $horses[2]->id])->assertRedirect(route('dashboard'));
        $user->unsetRelation('profile');
        app()->forgetInstance(HorseAccess::class);
        $this->assertTrue($this->actingAs($user)->get(route('dashboard'))->viewData('horse')->is($horses[2]));
    }

    public function test_stable_filters_and_search_with_many_horses(): void
    {
        [$user, $horses] = $this->owner(7);
        $this->allHorses($user);
        $horses[0]->update(['current_location' => 'Pré du haut']);
        HealthObservation::forceCreate(['horse_id' => $horses[1]->id, 'author_id' => $user->id, 'severity' => 'watch', 'body' => 'Toux', 'observed_at' => now()]);
        Treatment::forceCreate(['horse_id' => $horses[2]->id, 'author_id' => $user->id, 'product' => 'Vermifuge', 'starts_on' => now()->toDateString(), 'status' => 'active']);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-stable-filters', false)
            ->assertSee('data-stable-filter="loc:Pré du haut"', false)->assertSee('data-focus-search', false)->getContent();
        $row = fn ($h) => Str::betweenFirst($html, 'data-stable-horse="'.$h->id.'"', '>');
        $this->assertStringContainsString('watch', html_entity_decode($row($horses[1])));
        $this->assertStringContainsString('treat', html_entity_decode($row($horses[2])));
        $this->assertStringContainsString('loc:Pré du haut', html_entity_decode($row($horses[0])));
        $this->assertStringNotContainsString('watch', html_entity_decode($row($horses[3])));
    }

    public function test_budget_list_offers_edit_on_every_expense_the_user_may_change(): void
    {
        [$owner, $horses] = $this->owner(1);
        $horse = $horses[0];
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW, Perm::EXPENSES_VIEW, Perm::EXPENSES_CREATE]]);
        $cat = ExpenseCategory::firstOrFail();
        $mk = fn ($author, $amount) => Expense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $author->id, 'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => $amount, 'spent_on' => now()->toDateString(), 'paid_on' => now()->toDateString()]);
        $byOwner = $mk($owner, 70);
        $byRider = $mk($rider, 107.2);

        // Propriétaire : « Modifier » aussi sur la dépense saisie par la demi-pensionnaire (comme sur sa fiche).
        $html = $this->actingAs($owner)->get(route('budget.index'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-expense-edit') / 2, 'Mobile et ordinateur : un lien par dépense.');
        $this->actingAs($owner)->get(route('budget.edit', $byRider))->assertOk();

        // Demi-pensionnaire : seulement ses propres dépenses.
        $this->allHorses($rider);
        $html = $this->actingAs($rider)->get(route('budget.index'))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('budget.edit', $byRider).'" class="link" data-expense-edit', $html);
        $this->assertStringNotContainsString('href="'.route('budget.edit', $byOwner).'" class="link" data-expense-edit', $html);
        $this->actingAs($rider)->get(route('budget.edit', $byOwner))->assertForbidden();
    }
}
