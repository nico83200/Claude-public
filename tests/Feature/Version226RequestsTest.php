<?php

namespace Tests\Feature;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HealthObservation;
use App\Models\HorseAccessGrant;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Models\TreatmentAdministration;
use App\Support\Perm;
use Tests\TestCase;

/** Mode « un cheval » (lot B) : l'accueil est le tableau de bord du cheval. */
class Version226RequestsTest extends TestCase
{
    private function owner(string $plan = 'particulier'): array
    {
        $user = $this->user();
        if ($plan !== 'decouverte') {
            $this->grantLicense($user->personalOrganization(), $plan);
        }

        return [$user, $this->horseFor($user, null, ['official_name' => 'JACKPOT DU LAC', 'usual_name' => 'Jack'])];
    }

    public function test_home_is_the_horse_dashboard_with_one_tap_all_good(): void
    {
        [$user, $horse] = $this->owner();
        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard-solo');
        $page->assertSee('data-solo-hero', false)->assertSee('data-solo-state="slate"', false)->assertSee('Pas encore de suivi aujourd\'hui')
            ->assertSee('data-solo-all-good', false)->assertDontSee('Vos chevaux');

        // « Tout va bien » depuis l'accueil : retour sur l'accueil, état vert, bouton remplacé.
        $this->actingAs($user)->from(route('dashboard'))->post(route('horses.logs.store', $horse), ['appetite' => 'good', 'general_state' => 'good', 'all_good' => '1'])
            ->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('dashboard'))->assertSee('data-solo-state="green"', false)->assertSee('data-solo-log-done', false)
            ->assertDontSee('data-solo-all-good', false)->assertSee('data-solo-log-days>1', false);

        // Une alerte de santé passe en tête.
        HealthObservation::forceCreate(['horse_id' => $horse->id, 'author_id' => $user->id, 'severity' => 'alert', 'body' => 'Boiterie antérieur droit', 'observed_at' => now()]);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('data-solo-state="red"', false)->assertSee('Boiterie antérieur droit');
    }

    public function test_week_block_with_treatments_to_give_and_due_checks(): void
    {
        [$user, $horse] = $this->owner();
        $treatment = Treatment::forceCreate(['horse_id' => $horse->id, 'author_id' => $user->id, 'product' => 'Equipalazone', 'dosage' => '1 sachet', 'starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->addDays(4)->toDateString(), 'status' => 'active', 'track_administrations' => true, 'times_per_day' => 2]);
        $farrier = CareCategory::where('key', 'farrier')->firstOrFail();
        $care = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $farrier->id, 'author_id' => $user->id, 'performed_at' => now()->subWeeks(7), 'next_check_on' => now()->subDays(2)->toDateString()]);

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $page->assertSee('data-solo-treatment="'.$treatment->id.'"', false)->assertSee('Administré (0/2)')
            ->assertSee('data-solo-due="'.$care->id.'"', false)->assertSee(route('horses.care.create', [$horse, 'suite' => $care->id]), false)
            ->assertSee('data-solo-state="amber"', false);

        $this->actingAs($user)->from(route('dashboard'))->post(route('horses.treatments.administer', [$horse, $treatment]))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->post(route('horses.treatments.administer', [$horse, $treatment]));
        $this->assertSame(2, TreatmentAdministration::where('treatment_id', $treatment->id)->count());
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('data-solo-administer', false)->assertSee('Fait');
    }

    public function test_activity_budget_and_sharing_blocks(): void
    {
        [$user, $horse] = $this->owner();
        RidingSession::forceCreate(['horse_id' => $horse->id, 'created_by' => $user->id, 'rider_id' => $user->id, 'scheduled_at' => now()->subMinutes(30), 'session_type' => 'free', 'status' => 'completed', 'progress' => 'Très bonne détente']);
        $cat = ExpenseCategory::firstOrFail();
        Expense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $user->id, 'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => 120, 'spent_on' => now()->toDateString(), 'paid_on' => null]);
        $rider = $this->user(['name' => 'Léa Martin']);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW]]);

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $page->assertSee('data-solo-sessions-week>1', false)->assertSee('Très bonne détente')
            ->assertSee('data-solo-budget', false)->assertSee('120,00 €')
            ->assertSee('data-solo-shares', false)->assertSee('Léa Martin');
        $this->assertSame(120.0, $page->viewData('budget')['unpaid']);

        // Raccourci « Dépense » : ouvre le formulaire du budget.
        $this->actingAs($user)->get(route('budget.index', ['nouvelle' => 'depense']))->assertOk()->assertSee("document.getElementById('new-expense').showModal()</script>", false);
    }

    public function test_discovery_offer_shows_the_budget_teaser(): void
    {
        [$user] = $this->owner('decouverte');
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-solo-budget-locked', false)->assertDontSee('data-solo-budget>', false);
    }

    public function test_half_lease_rider_dashboard_shows_only_what_was_shared(): void
    {
        [$owner, $horse] = $this->owner();
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => [Perm::HORSE_VIEW, Perm::SESSIONS_VIEW, Perm::SESSIONS_CREATE, Perm::DAILYLOG_CREATE]]);
        $page = $this->actingAs($rider)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard-solo');
        $page->assertSee('data-solo-all-good', false)->assertSee('Je viens de monter')->assertSee('data-solo-activity', false)
            ->assertDontSee('data-solo-health', false)->assertDontSee('data-solo-budget', false)->assertDontSee('data-solo-shares', false)
            ->assertDontSee('Noter un soin');
    }

    public function test_several_horses_keep_the_usual_dashboard(): void
    {
        [$user] = $this->owner('multi-chevaux');
        $this->horseFor($user);
        $this->allHorses($user);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard')->assertSee('Vos chevaux');
    }
}
