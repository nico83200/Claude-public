<?php

namespace Tests\Feature;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Exercise;
use App\Models\ExpenseCategory;
use App\Models\HorseAccessGrant;
use App\Models\Income;
use App\Models\RidingSession;
use App\Models\SessionTemplate;
use App\Services\RidingSessionService;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Demandes de la version 2.22. */
class Version222RequestsTest extends TestCase
{
    private function owner(): array
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');

        return [$user, $this->horseFor($user, null, ['official_name' => 'JACK', 'usual_name' => 'Jack'])];
    }

    private function sessionData(array $extra = []): array
    {
        return $extra + ['scheduled_at' => now()->addDay()->format('Y-m-d').' 10:00', 'planned_minutes' => 45];
    }

    public function test_only_four_session_types_are_offered(): void
    {
        [$user, $horse] = $this->owner();
        $page = $this->actingAs($user)->get(route('sessions.create', ['horse' => $horse->id]))->assertOk();
        $select = Str::betweenFirst($page->getContent(), 'name="session_type"', '</select>');
        foreach (['Cours particulier', 'Cours collectif', 'Monte libre', 'Balade'] as $label) {
            $this->assertStringContainsString($label, $select);
        }
        foreach (['Dressage', 'Longe', 'Travail à pied', 'Obstacle'] as $label) {
            $this->assertStringNotContainsString($label, $select);
        }
        $this->assertSame(4, substr_count($select, '<option'));

        // Ancien type refusé pour une nouvelle séance…
        $this->actingAs($user)->post('/seances', $this->sessionData(['horse_id' => $horse->id, 'session_type' => 'dressage']))->assertSessionHasErrors('session_type');

        // … mais une séance déjà enregistrée avec un ancien type se modifie sans le perdre.
        $old = app(RidingSessionService::class)->create($horse, $user, ['scheduled_at' => now()->addDays(2), 'session_type' => 'dressage', 'planned_minutes' => 45]);
        $this->actingAs($user)->get(route('sessions.details', $old))->assertOk()->assertSee('Dressage');
        $this->actingAs($user)->put(route('sessions.update', $old), $this->sessionData(['session_type' => 'dressage', 'objective' => 'Rectitude']))->assertSessionHas('success');
        $this->assertSame(['dressage', 'Rectitude', 'Dressage'], [$old->fresh()->session_type, $old->fresh()->objective, $old->fresh()->typeLabel()]);
        $this->actingAs($user)->put(route('sessions.update', $old), $this->sessionData(['session_type' => 'jumping']))->assertSessionHasErrors('session_type');
    }

    public function test_lessons_and_rides_have_no_exercise_preparation(): void
    {
        [$user, $horse] = $this->owner();
        foreach (['private_lesson', 'group_lesson', 'ride'] as $type) {
            $res = $this->actingAs($user)->post('/seances', $this->sessionData(['horse_id' => $horse->id, 'session_type' => $type]));
            $session = RidingSession::latest('id')->firstOrFail();
            $res->assertRedirect(route('sessions.show', $session));
            $this->actingAs($user)->get(route('sessions.edit', $session))->assertRedirect(route('sessions.details', $session));
            $this->actingAs($user)->get(route('sessions.show', $session))->assertOk()->assertDontSee('data-prepare-link', false)->assertSee('data-debrief-link', false);
            $this->actingAs($user)->get(route('sessions.details', $session))->assertOk()->assertDontSee('utilisez <a href="'.route('sessions.edit', $session), false);
        }

        // Monte libre : préparation des exercices comme avant.
        $res = $this->actingAs($user)->post('/seances', $this->sessionData(['horse_id' => $horse->id, 'session_type' => 'free']));
        $free = RidingSession::latest('id')->firstOrFail();
        $res->assertRedirect(route('sessions.edit', $free));
        $this->actingAs($user)->get(route('sessions.edit', $free))->assertOk()->assertSee('data-exercise-picker', false);
        $this->actingAs($user)->get(route('sessions.show', $free))->assertSee('data-prepare-link', false);
    }

    public function test_a_lesson_from_a_template_or_a_copy_takes_no_exercises(): void
    {
        [$user, $horse] = $this->owner();
        $exercise = Exercise::whereNull('organization_id')->firstOrFail();
        $free = app(RidingSessionService::class)->create($horse, $user, ['scheduled_at' => now()->addDay(), 'session_type' => 'free', 'planned_minutes' => 45], [['exercise_id' => $exercise->id, 'phase' => 'main']]);
        $template = SessionTemplate::forceCreate(['user_id' => $user->id, 'name' => 'Plat', 'session_type' => 'free', 'planned_minutes' => 45,
            'items' => [['exercise_id' => $exercise->id, 'phase' => 'main']]]);

        $this->actingAs($user)->post('/seances', $this->sessionData(['horse_id' => $horse->id, 'session_type' => 'group_lesson', 'template_id' => $template->id]))->assertRedirect();
        $this->assertSame(0, RidingSession::latest('id')->firstOrFail()->items()->count());
        $this->actingAs($user)->post('/seances', $this->sessionData(['horse_id' => $horse->id, 'session_type' => 'free', 'template_id' => $template->id]))->assertRedirect();
        $this->assertSame(1, RidingSession::latest('id')->firstOrFail()->items()->count());

        $free->update(['session_type' => 'ride']);
        $this->actingAs($user)->post(route('sessions.duplicate', $free))->assertRedirect();
        $copy = RidingSession::latest('id')->firstOrFail();
        $this->assertSame([0, 'ride'], [$copy->items()->count(), $copy->session_type]);
    }

    public function test_prescription_can_be_read_from_the_health_menu_page(): void
    {
        config(['services.anthropic.key' => 'sk-ant-test']);
        [$user, $jack] = $this->owner();
        $page = $this->actingAs($user)->get('/sante')->assertOk()->assertSee('data-prescription-overview', false)
            ->assertSee('action="'.route('horses.prescription.read', $jack).'"', false);
        $this->assertStringNotContainsString('id="rx_horse"', $page->getContent(), 'Un seul cheval : pas de choix.');

        $uni = $this->horseFor($user, null, ['official_name' => 'UNI']);
        $two = $this->actingAs($user)->get('/sante')->assertOk()->assertSee('id="rx_horse"', false)->getContent();
        $this->assertStringContainsString('<option value="'.$uni->id.'">', Str::betweenFirst($two, 'id="rx_horse"', '</select>'));
        // Adresse d'envoi de chaque cheval (choisie par le menu, sans recharger).
        $this->assertStringContainsString('\\u0022'.$uni->id.'\\u0022:', $two);
        $this->assertStringContainsString('chevaux\\\\\\/'.$uni->id.'\\\\\\/ordonnance', $two);

        // Offre sans assistant : proposition d'offre au lieu de l'envoi.
        $free = $this->user();
        $this->horseFor($free);
        $this->actingAs($free)->get('/sante')->assertOk()->assertSee('data-prescription-overview', false)
            ->assertSee(route('billing.index', ['fonction' => 'assistant']), false)->assertDontSee('name="prescription"', false);

        // Santé en lecture seule : rien.
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $jack->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'health.view']]);
        $this->actingAs($viewer)->get('/sante')->assertOk()->assertDontSee('data-prescription-overview', false);
    }

    public function test_usual_rhythm_is_shown_per_category_when_creating_a_care(): void
    {
        [$user, $horse] = $this->owner();
        $cat = CareCategory::where('key', 'farrier')->first();
        foreach ([28, 21, 14, 7] as $weeks) {
            CareRecord::forceCreate(['uuid' => (string) Str::uuid(), 'horse_id' => $horse->id, 'care_category_id' => $cat->id, 'author_id' => $user->id, 'performed_at' => today()->subWeeks($weeks)->setTime(10, 0)]);
        }

        $page = $this->actingAs($user)->get(route('horses.care.create', $horse))->assertOk()->assertSee('data-rhythm-info', false);
        $this->assertStringContainsString('Rythme habituel : toutes les 7 semaines en moyenne (4 passages)', html_entity_decode($page->getContent()));
        $this->assertStringContainsString('\\u0022'.$cat->id.'\\u0022:\\u0022Rythme habituel', $page->getContent(), 'Information rattachée à la catégorie Maréchal-ferrant.');
        $this->actingAs($user)->get(route('horses.care.index', $horse))->assertOk()->assertDontSee('Rythme habituel');
        $existing = CareRecord::where('horse_id', $horse->id)->firstOrFail();
        $this->assertStringNotContainsString('Rythme habituel :', html_entity_decode($this->actingAs($user)->get(route('horses.care.edit', [$horse, $existing]))->getContent()));
    }

    public function test_incomes_are_recorded_listed_and_counted_in_the_balance(): void
    {
        [$user, $horse] = $this->owner();
        $this->actingAs($user)->get('/budget')->assertOk()->assertSee('data-new-income', false)->assertSee('Demi-pension')->assertDontSee('data-income-kpis', false);

        $this->actingAs($user)->post(route('budget.incomes.store'), ['horse_id' => $horse->id, 'category' => 'half_lease', 'received_on' => now()->toDateString(), 'amount' => '150', 'payer' => 'Léa'])
            ->assertSessionHas('success', 'Recette de 150,00 € enregistrée.');
        $this->actingAs($user)->post(route('budget.incomes.store'), ['horse_id' => $horse->id, 'category' => 'prize', 'received_on' => now()->toDateString(), 'amount' => '80.50', 'comment' => 'CSO Hyères'])->assertSessionHas('success');
        $cat = ExpenseCategory::firstOrFail();
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => '300', 'spent_on' => now()->toDateString()]);

        $page = $this->actingAs($user)->get('/budget')->assertOk()->assertSee('data-income-kpis', false)->assertSee('CSO Hyères')->assertSee('Gains de concours')->assertSee('Léa');
        $kpis = $page->viewData('kpis');
        $this->assertSame([230.5, -69.5], [$kpis['income'], round($kpis['balance'], 2)]);
        $this->actingAs($user)->get('/budget?horse='.$horse->id)->assertOk()->assertSee('CSO Hyères');

        // Erreur de saisie : la fenêtre des recettes se rouvre avec le message.
        $this->actingAs($user)->from('/budget')->post(route('budget.incomes.store'), ['horse_id' => $horse->id, 'received_on' => now()->toDateString(), 'amount' => '10'])
            ->assertSessionHasErrorsIn('income', 'category');

        // Modifier puis supprimer.
        $prize = Income::where('category', 'prize')->sole();
        $this->actingAs($user)->get(route('budget.incomes.edit', $prize))->assertOk()->assertSee('CSO Hyères');
        $this->actingAs($user)->put(route('budget.incomes.update', $prize), ['horse_id' => $horse->id, 'category' => 'prize', 'received_on' => now()->toDateString(), 'amount' => '120', 'comment' => 'CSO Hyères'])->assertSessionHas('success');
        $this->assertSame('120.00', $prize->fresh()->amount);
        $this->actingAs($user)->delete(route('budget.incomes.destroy', $prize))->assertSessionHas('success');
        $this->assertSoftDeleted($prize);

        // Autres utilisateurs : rien de visible ni de modifiable sans droit sur les dépenses du cheval.
        $stranger = $this->user();
        $this->actingAs($stranger)->get('/budget')->assertOk()->assertDontSee('Léa');
        $half = Income::where('category', 'half_lease')->sole();
        $this->actingAs($stranger)->get(route('budget.incomes.edit', $half))->assertForbidden();
        $this->actingAs($stranger)->delete(route('budget.incomes.destroy', $half))->assertForbidden();
        $this->actingAs($stranger)->post(route('budget.incomes.store'), ['horse_id' => $horse->id, 'category' => 'prize', 'received_on' => now()->toDateString(), 'amount' => '5'])->assertForbidden();

        // Lecture seule des dépenses : recettes visibles, sans modification.
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'expenses.view']]);
        $this->actingAs($viewer)->get('/budget')->assertOk()->assertSee('Léa')->assertDontSee(route('budget.incomes.edit', $half));
        $this->actingAs($viewer)->put(route('budget.incomes.update', $half), ['horse_id' => $horse->id, 'category' => 'prize', 'received_on' => now()->toDateString(), 'amount' => '1'])->assertForbidden();
    }
}
