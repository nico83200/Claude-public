<?php

namespace Tests\Feature;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invitation;
use App\Models\Professional;
use App\Models\RecurringExpense;
use App\Notifications\AppNotification;
use App\Services\HorseAccess;
use App\Services\VetSummary;
use App\Support\Fr;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Retours de l'audit UX (parties 1 à 3) : version 2.24. */
class Version224RequestsTest extends TestCase
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

    public function test_validation_messages_are_in_french(): void
    {
        $this->post('/register', ['name' => '', 'email' => 'pas-une-adresse'])
            ->assertSessionHasErrors(['name' => 'Le nom est obligatoire.']);
        $this->assertSame('fr', app()->getLocale());
    }

    public function test_notification_opens_only_internal_pages(): void
    {
        [$user] = $this->owner();
        $mk = fn (string $url) => $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => AppNotification::class, 'data' => ['url' => $url]])->id;
        $this->actingAs($user)->get('/notifications/'.$mk('https://ancienne-adresse.test/budget?year=2026'))->assertRedirect('/budget?year=2026');
        $this->actingAs($user)->get('/notifications/'.$mk('https://ailleurs.test/page-inconnue'))
            ->assertRedirect(route('notifications.index'))->assertSessionHas('warning', 'Ce rappel n\'est plus disponible.');
    }

    public function test_dashboard_offers_quick_actions_and_just_rode_preselects_the_horse(): void
    {
        [$user, $horse] = $this->owner();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-solo-actions', false)
            ->assertSee(route('sessions.create', ['fait' => 1]), false)->assertSee('Je viens de monter');
        $page = $this->actingAs($user)->get(route('sessions.create', ['fait' => 1]))->assertOk();
        $this->assertTrue($page->viewData('justRode'));
        $page->assertSee('value="'.$horse->id.'" selected', false);
    }

    public function test_daily_log_one_tap_time_edit_and_delete(): void
    {
        [$user, $horse] = $this->owner();
        $this->actingAs($user)->get(route('health.index', ['onglet' => 'suivi']))->assertOk()->assertDontSee('data-log-all-good', false)->assertSee('data-log-detailed', false);

        $this->actingAs($user)->post(route('horses.logs.store', $horse), ['appetite' => 'good', 'general_state' => 'good', 'all_good' => '1'])
            ->assertSessionHas('success', 'C\'est noté : tout va bien aujourd\'hui.');
        $this->actingAs($user)->post(route('horses.logs.store', $horse), ['logged_at' => '2026-10-14 18:30', 'appetite' => 'reduced', 'general_state' => 'average', 'observations' => 'Un peu raide'])->assertSessionHas('success');
        $log = DailyLog::where('appetite', 'reduced')->sole();
        $this->assertSame('2026-10-14 18:30', $log->logged_at->format('Y-m-d H:i'));
        $this->actingAs($user)->get(route('horses.logs.index', $horse))->assertOk()->assertSee(route('horses.logs.edit', [$horse, $log]), false);

        $this->actingAs($user)->put(route('horses.logs.update', [$horse, $log]), ['logged_at' => '2026-10-14 19:00', 'appetite' => 'good', 'general_state' => 'good', 'observations' => 'Finalement en forme'])
            ->assertRedirect(route('horses.logs.index', $horse));
        $this->assertSame(['good', 'Finalement en forme', '19:00'], [$log->fresh()->appetite, $log->fresh()->observations, $log->fresh()->logged_at->format('H:i')]);

        // Une personne qui a seulement accès en lecture ne peut ni modifier ni supprimer.
        $reader = $this->user();
        $horse->accessGrants()->create(['user_id' => $reader->id, 'permissions' => [Perm::HORSE_VIEW], 'granted_by' => $user->id, 'label' => 'Lecture']);
        app()->forgetInstance(HorseAccess::class);
        $this->actingAs($reader)->get(route('horses.logs.edit', [$horse, $log]))->assertForbidden();
        $this->actingAs($reader)->delete(route('horses.logs.destroy', [$horse, $log]))->assertForbidden();

        // Une saisie d'un autre cheval n'est pas accessible par ce dossier.
        $other = $this->horseFor($user);
        $this->actingAs($user)->delete(route('horses.logs.destroy', [$other, $log]))->assertNotFound();

        $this->actingAs($user)->delete(route('horses.logs.destroy', [$horse, $log]))->assertRedirect(route('horses.logs.index', $horse));
        $this->assertSame(1, DailyLog::count());
    }

    public function test_vet_summary_includes_emergency_notes(): void
    {
        [$user, $horse] = $this->owner();
        $horse->forceFill(['emergency_notes' => 'Allergique à la pénicilline'])->save();
        $s = app(VetSummary::class)->build($horse->fresh(), 6);
        $this->assertSame('Allergique à la pénicilline', $s['emergency_notes']);
        $this->assertStringContainsString('Allergique à la pénicilline', app(VetSummary::class)->text($s));
        $this->assertStringContainsString('Allergies et notes d\'urgence', view('health._vet-summary-body', ['s' => $s])->render());
    }

    public function test_note_the_visit_from_a_due_check_and_usual_professional(): void
    {
        [$user, $horse] = $this->owner();
        $farrier = CareCategory::where('key', 'farrier')->firstOrFail();
        $pro = Professional::forceCreate(['organization_id' => $horse->organization_id, 'first_name' => 'Julien', 'last_name' => 'Moreau', 'kind' => 'farrier']);
        $care = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $farrier->id, 'professional_id' => $pro->id, 'author_id' => $user->id,
            'performed_at' => now()->subWeeks(7), 'next_check_on' => now()->subDay()->toDateString()]);

        $this->actingAs($user)->get(route('horses.care.index', $horse))->assertOk()
            ->assertSee(route('horses.care.create', [$horse, 'suite' => $care->id]), false)->assertSee('Noter le passage');
        $form = $this->actingAs($user)->get(route('horses.care.create', [$horse, 'suite' => $care->id]))->assertOk();
        $this->assertSame([$farrier->id, $pro->id], [$form->viewData('record')->care_category_id, $form->viewData('record')->professional_id]);
        $this->assertSame([(string) $farrier->id => (string) $pro->id], $form->viewData('usualPros'));

        // Le nouveau passage clôt l'échéance.
        $this->actingAs($user)->post(route('horses.care.store', $horse), ['care_category_id' => $farrier->id, 'professional_id' => $pro->id, 'performed_at' => now()->format('Y-m-d\TH:i'), 'next_mode' => 'none'])->assertRedirect();
        $this->assertNotNull($care->fresh()->next_check_done_at);
    }

    public function test_budget_fixed_monthly_costs_unpay_and_recurring_comment(): void
    {
        Carbon::setTestNow('2026-10-16 10:00');
        [$user, $horse] = $this->owner();
        $cat = ExpenseCategory::firstOrFail();
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '380', 'spent_on' => '2026-10-01',
            'recurring' => '1', 'label' => 'Pension', 'frequency' => 'monthly', 'paid' => '1', 'comment' => 'Box avec paddock'])->assertSessionHas('success');
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '240', 'spent_on' => '2026-10-02',
            'recurring' => '1', 'label' => 'Assurance', 'frequency' => 'yearly', 'paid' => '1'])->assertSessionHas('success');

        $this->assertSame('Pension — Box avec paddock', Expense::where('amount', 380)->value('comment'));
        $page = $this->actingAs($user)->get('/budget')->assertOk()->assertSee('data-fixed-monthly', false);
        $this->assertEqualsWithDelta(400.0, $page->viewData('kpis')['fixed_monthly'], 0.001);

        // Suspendue : n'entre plus dans les charges fixes.
        RecurringExpense::where('label', 'Assurance')->update(['paused_at' => now()]);
        $this->assertEqualsWithDelta(380.0, $this->actingAs($user)->get('/budget')->viewData('kpis')['fixed_monthly'], 0.001);

        // « Payé » se défait depuis la fiche de la dépense.
        $expense = Expense::where('amount', 380)->sole();
        $this->actingAs($user)->get(route('budget.show', $expense))->assertOk()->assertSee('data-expense-unpay', false);
        $this->actingAs($user)->post(route('budget.pay', $expense), ['paid' => '0'])->assertSessionHas('success', 'Dépense remise « à payer ».');
        $this->assertNull($expense->fresh()->paid_on);
    }

    public function test_french_counts_and_sizes(): void
    {
        $this->assertSame(['1 cheval', '10 chevaux', '0 membre', '2 membres'], [Fr::count(1, 'cheval', 'chevaux'), Fr::count(10, 'cheval', 'chevaux'), Fr::count(0, 'membre', 'membres'), Fr::count(2, 'membre', 'membres')]);
        $this->assertSame(['500 Mo', '10 Go', '1,5 Go'], [Fr::megabytes(500), Fr::megabytes(10240), Fr::megabytes(1536)]);
        $this->get('/tarifs')->assertOk()->assertDontSee('chevalx')->assertDontSee('membre(s)');
    }

    public function test_invitation_link_can_be_sent_by_message_and_resent(): void
    {
        Notification::fake();
        [$user, $horse] = $this->owner();
        $this->actingAs($user)->post(route('horses.sharing.invite', $horse), ['email' => 'Lea@Example.com', 'permissions' => [Perm::HORSE_VIEW]])
            ->assertSessionHas('invite_link', fn ($l) => $l['email'] === 'lea@example.com' && str_contains($l['url'], '/invitations/'));
        $url = session('invite_link')['url'];
        $this->actingAs($user)->get(route('horses.sharing', $horse))->assertOk()->assertSee('data-invite-whatsapp', false)->assertSee('wa.me', false);
        $this->get($url)->assertOk();

        $invitation = Invitation::sole();
        $this->actingAs($user)->post(route('horses.sharing.invitations.resend', [$horse, $invitation]))->assertSessionHas('success', 'Invitation renvoyée à lea@example.com.');
        $newUrl = session('invite_link')['url'];
        $this->assertNotSame($url, $newUrl);
        $this->get($url)->assertNotFound();
        $this->get($newUrl)->assertOk();
        Notification::assertSentOnDemandTimes(AppNotification::class, 2);

        // Un autre propriétaire ne peut pas relancer cette invitation.
        [$other, $otherHorse] = $this->owner();
        $this->actingAs($other)->post(route('horses.sharing.invitations.resend', [$horse, $invitation]))->assertForbidden();
        $this->actingAs($other)->post(route('horses.sharing.invitations.resend', [$otherHorse, $invitation]))->assertNotFound();
    }

    public function test_usual_name_everywhere_and_note_the_visit_from_the_health_menu(): void
    {
        [$user, $horse] = $this->owner();
        $cat = ExpenseCategory::firstOrFail();
        Expense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $user->id, 'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => 50, 'spent_on' => now()->toDateString(), 'paid_on' => now()->toDateString()]);
        $budget = $this->actingAs($user)->get('/budget')->assertOk();
        $this->assertSame([$horse->id => 'Jack'], $budget->viewData('horses')->all());
        $this->assertSame(['Jack'], $budget->viewData('kpis')['by_horse']->keys()->all());
        $this->assertSame([$horse->id => 'Jack'], $this->actingAs($user)->get(route('sessions.create'))->viewData('horses')->all());

        $farrier = CareCategory::where('key', 'farrier')->firstOrFail();
        $care = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $farrier->id, 'author_id' => $user->id, 'performed_at' => now()->subWeeks(7), 'next_check_on' => now()->addWeek()->toDateString()]);
        // Plusieurs chevaux : la page Santé du menu liste les échéances de tous les chevaux.
        $this->horseFor($user, null, ['official_name' => 'UNI']);
        $this->allHorses($user);
        $this->actingAs($user)->get(route('health.index'))->assertOk()->assertSee('data-due="'.$care->id.'"', false)
            ->assertSee(route('horses.care.create', [$horse, 'suite' => $care->id]), false);

        // Lecture seule (un seul cheval, son dossier santé) : l'échéance est visible, sans le bouton.
        $reader = $this->user();
        $horse->accessGrants()->create(['user_id' => $reader->id, 'permissions' => [Perm::HORSE_VIEW, Perm::HEALTH_VIEW], 'granted_by' => $user->id, 'label' => 'Lecture']);
        app()->forgetInstance(HorseAccess::class);
        $this->actingAs($reader)->get(route('health.index'))->assertOk()->assertSee('data-next-check="'.$care->id.'"', false)->assertDontSee('data-note-passage', false);
    }
}
