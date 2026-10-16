<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeedingPlan;
use App\Models\HorseAccessGrant;
use App\Models\Professional;
use App\Models\RecurringExpense;
use App\Models\Treatment;
use App\Models\TreatmentAdministration;
use App\Notifications\AppNotification;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\ClaudeGateway;
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FakeClaude;
use Tests\TestCase;

/** Demandes de la version 2.21. */
class Version221RequestsTest extends TestCase
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

    public function test_assistant_is_limited_to_the_application(): void
    {
        [$user] = $this->owner();
        $fake = new FakeClaude([['text' => AssistantService::OUT_OF_SCOPE]]);
        $this->app->instance(ClaudeGateway::class, $fake);

        $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Quelle est la capitale du Pérou ?', 'mode' => 'voice'])->assertOk()
            ->assertJsonPath('reply', AssistantService::OUT_OF_SCOPE);
        $system = collect($fake->calls[0]['system'])->pluck('text')->implode("\n");
        $this->assertStringContainsString('Limité à l\'application', $system);
        $this->assertStringContainsString(AssistantService::OUT_OF_SCOPE, $system);
    }

    public function test_several_rations_at_once_with_duration_archive_and_delete(): void
    {
        Carbon::setTestNow('2026-10-15 10:00');
        [$user, $horse] = $this->owner();
        $entry = fn ($feed) => ['entries' => [['feed' => $feed, 'quantity' => '2', 'unit' => 'kg', 'time_of_day' => '08:00']]];

        $this->actingAs($user)->post(route('horses.feeding.store', $horse), ['name' => 'Ration du matin', 'starts_on' => '2026-10-01', 'end_mode' => 'none'] + $entry('Granulés'))->assertSessionHas('success');
        $this->actingAs($user)->post(route('horses.feeding.store', $horse), ['name' => 'Ration du soir', 'starts_on' => '2026-10-01', 'end_mode' => 'none'] + $entry('Foin'))->assertSessionHas('success');
        $this->actingAs($user)->post(route('horses.feeding.store', $horse), ['name' => 'Complément articulaire', 'starts_on' => '2026-10-15', 'end_mode' => 'duration', 'duration_value' => 15, 'duration_unit' => 'days'] + $entry('Chondroïtine'))->assertSessionHas('success');

        $supplement = FeedingPlan::where('name', 'Complément articulaire')->sole();
        $this->assertSame('2026-10-29', $supplement->ends_on->toDateString(), '15 jours, jour de début compris.');
        $this->actingAs($user)->get(route('horses.feeding', $horse))->assertOk()
            ->assertSee('Ration du matin')->assertSee('Ration du soir')->assertSee('Complément articulaire')->assertSee('Chondroïtine');
        $this->assertSame(3, FeedingPlan::where('horse_id', $horse->id)->activeToday()->count());

        // Modifier une ration : date de fin précise.
        $morning = FeedingPlan::where('name', 'Ration du matin')->sole();
        $this->actingAs($user)->put(route('horses.feeding.update', [$horse, $morning]), ['name' => 'Ration du matin', 'starts_on' => '2026-10-01', 'end_mode' => 'date', 'ends_on' => '2026-12-31'] + $entry('Orge'))->assertSessionHas('success');
        $this->assertSame(['2026-12-31', 'Orge'], [$morning->fresh()->ends_on->toDateString(), $morning->entries()->value('feed')]);

        // Archiver puis reprendre.
        $evening = FeedingPlan::where('name', 'Ration du soir')->sole();
        $this->actingAs($user)->post(route('horses.feeding.archive', [$horse, $evening]))->assertSessionHas('success');
        $this->assertTrue($evening->fresh()->isArchived());
        $this->actingAs($user)->get(route('horses.feeding', $horse))->assertSee('Rations archivées');
        $this->actingAs($user)->post(route('horses.feeding.archive', [$horse, $evening]), ['restore' => 1])->assertSessionHas('success');
        $this->assertFalse($evening->fresh()->isArchived());

        // Supprimer (erreur de saisie).
        $this->actingAs($user)->delete(route('horses.feeding.destroy', [$horse, $evening]))->assertSessionHas('success');
        $this->assertNull(FeedingPlan::find($evening->id));

        // Ration d'un autre cheval : introuvable par cette adresse.
        $other = $this->horseFor($user);
        $this->actingAs($user)->delete(route('horses.feeding.destroy', [$other, $morning]))->assertNotFound();

        // Lecture seule : pas de modification possible.
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'feeding.view']]);
        $this->actingAs($viewer)->get(route('horses.feeding', $horse))->assertOk()->assertSee('Ration du matin')->assertDontSee(route('horses.feeding.destroy', [$horse, $morning]));
        $this->actingAs($viewer)->delete(route('horses.feeding.destroy', [$horse, $morning]))->assertForbidden();
    }

    public function test_finished_ration_is_archived_automatically_with_a_notification(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-10-15 10:00');
        [$user, $horse] = $this->owner();
        $this->actingAs($user)->post(route('horses.feeding.store', $horse), ['name' => 'Cure de 15 jours', 'starts_on' => '2026-10-15', 'end_mode' => 'duration', 'duration_value' => 15, 'duration_unit' => 'days', 'entries' => [['feed' => 'Levure']]]);
        $plan = FeedingPlan::sole();

        $this->artisan('feeding:archive')->assertSuccessful();
        $this->assertNull($plan->fresh()->archived_at);
        Notification::assertNothingSent();

        Carbon::setTestNow('2026-10-30 07:10');
        $this->artisan('feeding:archive')->assertSuccessful();
        $this->artisan('feeding:archive')->assertSuccessful();
        $this->assertNotNull($plan->fresh()->archived_at);
        Notification::assertSentToTimes($user, AppNotification::class, 1);
        Notification::assertSentTo($user, AppNotification::class, fn (AppNotification $n) => $n->kind === 'feeding_end' && str_contains($n->body, 'Cure de 15 jours'));
    }

    public function test_a_treatment_entered_by_mistake_can_be_deleted(): void
    {
        [$user, $horse] = $this->owner();
        $this->actingAs($user)->post(route('horses.treatments.store', $horse), ['product' => 'Antibiotique X', 'starts_on' => now()->toDateString(), 'status' => 'active'])->assertSessionHas('success');
        $treatment = Treatment::sole();
        TreatmentAdministration::create(['treatment_id' => $treatment->id, 'administered_by' => $user->id, 'administered_at' => now()]);
        $this->assertSame(1, CalendarEvent::where('treatment_id', $treatment->id)->count());
        $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertSee('data-treatment-delete', false);

        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'health.view', 'treatments.view']]);
        $this->actingAs($viewer)->delete(route('horses.treatments.destroy', [$horse, $treatment]))->assertForbidden();

        $this->actingAs($user)->delete(route('horses.treatments.destroy', [$horse, $treatment]))->assertSessionHas('success');
        $this->assertSoftDeleted($treatment);
        $this->assertSame(0, TreatmentAdministration::where('treatment_id', $treatment->id)->count());
        $this->assertSame(0, CalendarEvent::where('treatment_id', $treatment->id)->count());
    }

    public function test_health_page_offers_care_buttons_with_a_horse_choice(): void
    {
        [$user, $jack] = $this->owner();
        // Un seul cheval : son dossier santé, bouton « Soin » direct.
        $this->actingAs($user)->get('/sante')->assertOk()
            ->assertSee('href="'.route('horses.care.create', $jack).'"', false)
            ->assertDontSee('data-horse-picker-sheet="care"', false);

        $uni = $this->horseFor($user, null, ['official_name' => 'UNI']);
        $this->actingAs($user)->get('/sante')->assertOk()->assertSee('data-horse-picker-sheet="care"', false)
            ->assertSee(route('horses.care.create', $jack))->assertSee(route('horses.care.create', $uni));

        // Santé visible mais non modifiable : pas de bouton de création.
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $jack->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'health.view']]);
        $this->actingAs($viewer)->get('/sante')->assertOk()->assertDontSee('data-horse-picker="care"', false);
    }

    public function test_care_documents_open_full_screen_in_the_viewer(): void
    {
        Storage::fake('local');
        [$user, $horse] = $this->owner();
        $this->actingAs($user)->post("/chevaux/{$horse->id}/soins", [
            'care_category_id' => CareCategory::where('key', 'veterinary')->value('id'), 'performed_at' => now()->format('Y-m-d H:i'), 'reason' => 'Boiterie',
            'attachment' => UploadedFile::fake()->create('compte-rendu.pdf', 40, 'application/pdf'),
        ])->assertRedirect();
        $care = CareRecord::sole();
        $doc = $care->documents()->sole();

        $this->actingAs($user)->get(route('horses.care.show', [$horse, $care]))->assertOk()
            ->assertSee('data-lightbox-type="pdf"', false)->assertSee('data-lightbox-download="'.route('horses.documents.download', [$horse, $doc]).'?telecharger=1"', false);
        $inline = $this->actingAs($user)->get(route('horses.documents.download', [$horse, $doc]))->assertOk();
        $this->assertStringStartsWith('inline', $inline->headers->get('Content-Disposition'));
        $this->assertSame('sandbox', $inline->headers->get('Content-Security-Policy'));
        $download = $this->actingAs($user)->get(route('horses.documents.download', [$horse, $doc]).'?telecharger=1')->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));
    }

    public function test_deleting_an_expense_offers_to_delete_its_care(): void
    {
        [$user, $horse] = $this->owner();
        $vet = ExpenseCategory::where('key', 'veterinary')->firstOrFail();
        $add = fn ($amount) => $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $vet->id, 'amount' => $amount, 'spent_on' => now()->toDateString(), 'add_care' => '1']);

        $add('80');
        $expense = Expense::where('amount', 80)->sole();
        $care = CareRecord::findOrFail($expense->care_record_id);
        // Glissé (téléphone) : la suppression du soin est proposée ensuite.
        $this->actingAs($user)->deleteJson("/budget/{$expense->id}")->assertOk()
            ->assertJsonPath('care.delete_url', route('horses.care.destroy', [$horse, $care]));
        $this->assertNotSoftDeleted($care);

        // Fenêtre de confirmation : case « supprimer aussi le soin » proposée, cochée ici.
        $add('120');
        $expense = Expense::where('amount', 120)->sole();
        $this->actingAs($user)->get(route('budget.edit', $expense))->assertOk()->assertSee('data-confirm-check="with_care"', false);
        $this->actingAs($user)->delete("/budget/{$expense->id}", ['with_care' => '1'])->assertSessionHas('success', 'Dépense et soin supprimés.');
        $this->assertSoftDeleted(CareRecord::withTrashed()->findOrFail($expense->care_record_id));

        // Sans droit de modifier la santé : rien n'est proposé ni supprimé côté soin.
        $add('60');
        $keeper = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $keeper->id, 'permissions' => ['horse.view', 'expenses.view', 'expenses.create']]);
        $expense = Expense::where('amount', 60)->sole();
        $expense->forceFill(['author_id' => $keeper->id])->save();
        $this->actingAs($keeper)->deleteJson("/budget/{$expense->id}", ['with_care' => '1'])->assertOk()->assertJsonMissingPath('care');
        $this->assertNotSoftDeleted(CareRecord::findOrFail($expense->care_record_id));
    }

    public function test_new_expense_only_offers_cares_without_an_expense(): void
    {
        [$user, $horse] = $this->owner();
        $category = CareCategory::where('key', 'farrier')->value('id');
        $mk = fn ($reason) => CareRecord::forceCreate(['uuid' => (string) Str::uuid(), 'horse_id' => $horse->id, 'care_category_id' => $category, 'author_id' => $user->id, 'performed_at' => now()->subDays(3), 'reason' => $reason]);
        $free = $mk('Parage libre');
        $taken = $mk('Ferrure payée');
        $farrierExpense = ExpenseCategory::where('key', 'farrier')->firstOrFail();
        Expense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $user->id, 'horse_id' => $horse->id, 'expense_category_id' => $farrierExpense->id, 'amount' => 90, 'spent_on' => now()->subDays(3), 'care_record_id' => $taken->id]);

        $open = $this->actingAs($user)->get('/budget')->assertOk()->viewData('openCares');
        $this->assertSame([$free->id], $open[$horse->id]->pluck('id')->all());

        // Rattacher à un soin déjà payé : refusé (dépense enregistrée sans lien).
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $farrierExpense->id, 'amount' => '45', 'spent_on' => now()->toDateString(), 'care_link' => (string) $taken->id]);
        $this->assertNull(Expense::where('amount', 45)->value('care_record_id'));
        $this->assertSame(1, Expense::where('care_record_id', $taken->id)->count());

        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $farrierExpense->id, 'amount' => '55', 'spent_on' => now()->toDateString(), 'care_link' => (string) $free->id])
            ->assertSessionHas('success', 'Dépense enregistrée et rattachée au soin du '.$free->performed_at->format('d/m/Y').'.');
        $this->assertSame($free->id, Expense::where('amount', 55)->value('care_record_id'));
        $this->assertSame('55.00', $free->fresh()->cost);
        $this->assertSame(2, CareRecord::count(), 'Aucun soin en double.');
    }

    public function test_phone_numbers_are_shown_as_pairs_of_digits(): void
    {
        $this->assertSame('06 12 34 56 78', Phone::format('0612345678'));
        $this->assertSame('06 12 34 56 78', Phone::format('+33 6 12 34 56 78'));
        $this->assertSame('04 78 12 34 56', Phone::format('04.78.12.34.56'));
        $this->assertSame('+44 20 7946 0958', Phone::format('+44 20 7946 0958'));
        $this->assertNull(Phone::format('  '));

        [$user, $horse] = $this->owner();
        $pro = Professional::forceCreate(['organization_id' => $horse->organization_id, 'kind' => 'farrier', 'last_name' => 'Martin', 'phone' => '0612345678']);
        $this->assertSame('06 12 34 56 78', $pro->fresh()->phone);
        $this->actingAs($user)->get(route('professionals.show', $pro))->assertOk()->assertSee('06 12 34 56 78')->assertSee('tel:0612345678', false);
    }

    public function test_only_the_next_check_of_a_care_type_can_be_added_to_the_agenda(): void
    {
        [$user, $horse] = $this->owner();
        $farrier = CareCategory::where('key', 'farrier')->value('id');
        $old = CareRecord::forceCreate(['uuid' => (string) Str::uuid(), 'horse_id' => $horse->id, 'care_category_id' => $farrier, 'author_id' => $user->id, 'performed_at' => now()->subWeeks(14), 'next_check_on' => now()->subWeeks(6)->toDateString()]);
        $last = CareRecord::forceCreate(['uuid' => (string) Str::uuid(), 'horse_id' => $horse->id, 'care_category_id' => $farrier, 'author_id' => $user->id, 'performed_at' => now()->subWeeks(2), 'next_check_on' => now()->addWeeks(5)->toDateString()]);
        $this->assertSame(0, CalendarEvent::whereNotNull('source_type')->count());

        $page = $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertOk();
        $page->assertSee('data-next-check="'.$last->id.'"', false)->assertDontSee('data-next-check="'.$old->id.'"', false);
        $this->actingAs($user)->post(route('horses.care.check.plan', [$horse, $old]))->assertStatus(422);
        $this->actingAs($user)->post(route('horses.care.check.plan', [$horse, $last]))->assertSessionHas('success');
        $this->assertStringNotContainsString('Contrôle', CalendarEvent::where('source_id', $last->id)->sole()->title);
    }

    public function test_budget_can_show_all_dates(): void
    {
        Carbon::setTestNow('2026-10-15 10:00');
        [$user, $horse] = $this->owner();
        $cat = ExpenseCategory::firstOrFail();
        foreach (['2024-03-01' => 100, '2025-06-01' => 200, '2026-02-01' => 300] as $date => $amount) {
            Expense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $user->id, 'horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'amount' => $amount, 'spent_on' => $date, 'paid_on' => $date]);
        }

        $this->actingAs($user)->get('/budget')->assertOk()->assertSee('data-year-all', false)->assertViewHas('kpis', fn ($k) => (float) $k['year'] === 300.0);
        $all = $this->actingAs($user)->get('/budget?year=toutes')->assertOk()->assertSee('Toutes les dates')->assertSee('Évolution par année');
        $kpis = $all->viewData('kpis');
        $this->assertSame(600.0, (float) $kpis['year']);
        $this->assertSame(['2024', '2025', '2026'], array_column($kpis['series'], 'short'));
        $this->assertSame([100.0, 200.0, 300.0], array_column($kpis['series'], 'value'));
        // Mars 2024 → octobre 2026 : 32 mois.
        $this->assertEqualsWithDelta(600 / 32, $kpis['per_month'], 0.01);
    }

    public function test_only_ongoing_recurring_expenses_are_listed_with_a_button_for_ended_ones(): void
    {
        Carbon::setTestNow('2026-10-15 10:00');
        [$user, $horse] = $this->owner();
        $cat = ExpenseCategory::firstOrFail();
        $mk = fn ($label, $ends) => RecurringExpense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $user->id, 'horse_id' => $horse->id, 'expense_category_id' => $cat->id,
            'label' => $label, 'amount' => 50, 'frequency' => 'monthly', 'starts_on' => '2026-01-05', 'next_due_on' => $ends ? '2026-07-05' : '2026-11-05', 'ends_on' => $ends]);
        $ongoing = $mk('Pension en cours', null);
        $ended = $mk('Assurance terminée', '2026-06-30');

        $this->actingAs($user)->get('/budget')->assertOk()
            ->assertSee('Pension en cours')->assertSee('data-recurring-toggle', false)->assertSee('Voir les récurrences terminées (1)')
            ->assertSeeInOrder(['data-recurring-ongoing', 'Pension en cours', 'data-recurring-ended', 'Assurance terminée'], false);
        $this->assertTrue($ended->isEnded());
        $this->assertFalse($ongoing->isEnded());
    }
}
