<?php

namespace Tests\Feature;

use App\Models\BoardingStatement;
use App\Models\Expense;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseOrganizationAssignment;
use App\Models\LessonCharge;
use App\Models\Professional;
use App\Models\User;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Cours donnés par les enseignants de l'écurie : repris sur les relevés, au nom de la personne qui paie. */
class Version245RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Écurie avec son enseignant Jimmy, Uni (à Paul) en pension, Léa en demi-pension. */
    private function setUpStable(): array
    {
        Carbon::setTestNow('2026-10-17 18:00');
        $manager = $this->user(['name' => 'Marc ROUX']);
        $stable = $this->stable($manager, 'Écurie des Pins');
        $manager->forceFill(['current_organization_id' => $stable->id])->save();
        $paul = $this->user(['name' => 'Paul MARTIN', 'email' => 'paul@exemple.fr']);
        $this->grantLicense($paul->personalOrganization(), 'particulier');
        $horse = $this->horseFor($paul, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        HorseOrganizationAssignment::create(['horse_id' => $horse->id, 'organization_id' => $stable->id, 'kind' => 'boarding',
            'permissions' => [...Perm::boardingPreset(), Perm::SESSIONS_CREATE], 'status' => 'active', 'starts_at' => now()->subYear()]);
        $lea = $this->user(['name' => 'Léa DURAND']);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $lea->id, 'label' => 'Demi-pension', 'permissions' => Perm::halfLeasePreset()]);
        $jimmy = Professional::forceCreate(['organization_id' => $stable->id, 'first_name' => 'Jimmy', 'last_name' => 'BLANC', 'kind' => 'instructor']);
        $jimmy->rates()->create(['kind' => 'private_lesson', 'unit' => 'session', 'duration_minutes' => 45, 'amount' => 40]);
        $jimmy->rates()->create(['kind' => 'group_lesson', 'unit' => 'session', 'amount' => 25]);
        $manager = $manager->fresh();
        $this->actingAs($manager)->post(route('boarding.contracts.store'), ['horse_id' => $horse->id, 'owner_name' => 'Paul MARTIN', 'owner_email' => 'paul@exemple.fr',
            'label' => 'Pension box', 'monthly_amount' => '420', 'starts_on' => '2026-10-01'])->assertSessionHas('success');

        return [$manager, $stable, $paul, $horse, $lea];
    }

    /** Cours noté par l'équipe de l'écurie avec Jimmy. */
    private function lesson(User $by, Horse $horse, string $at, ?User $rider, string $type = 'private_lesson'): void
    {
        $this->fresh();
        $this->actingAs($by)->post(route('sessions.store'), array_filter(['horse_id' => $horse->id, 'scheduled_at' => $at, 'session_type' => $type,
            'planned_minutes' => 45, 'instructor' => 'Jimmy BLANC', 'rider_id' => $rider?->id]))->assertSessionHasNoErrors();
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
    }

    private function statementOf(User $user): BoardingStatement
    {
        return BoardingStatement::where('owner_user_id', $user->id)->sole();
    }

    public function test_lessons_join_the_owner_statement_and_make_a_lesson_statement_for_the_half_lease(): void
    {
        [$manager, $stable, $paul, $horse, $lea] = $this->setUpStable();
        $this->lesson($manager, $horse, '2026-10-06 10:00', $paul);
        $this->lesson($manager, $horse, '2026-10-08 10:00', $lea);
        $this->lesson($manager, $horse, '2026-10-10 10:00', $lea, 'group_lesson');
        // Cours noté sans cavalier sur un cheval de l'écurie : à la charge de l'équipe, pas de relevé à soi-même.
        $school = $this->horseFor($manager, $stable, ['official_name' => 'PRINCE DE L\'ÉCOLE', 'usual_name' => 'Prince']);
        $this->lesson($manager, $school, '2026-10-09 10:00', null);
        $this->assertSame(['40.00', '40.00', '25.00', '40.00'], LessonCharge::orderBy('id')->pluck('amount')->all());

        $this->actingAs($manager)->get(route('boarding.index', ['mois' => '2026-10']))->assertOk()->assertSee('data-stable-lessons', false)->assertSee('Léa DURAND')
            ->assertSee('Cours collectif');
        $this->actingAs($manager)->post(route('boarding.generate'), ['period' => '2026-10'])->assertSessionHas('success');
        $this->assertSame(2, BoardingStatement::count(), 'Paul et Léa ; personne de l\'équipe.');
        $ofPaul = $this->statementOf($paul);
        $this->assertSame(['Relevé de pension et de cours', '460.00', ['pension', 'lesson']], [$ofPaul->title(), $ofPaul->total, array_column($ofPaul->lines, 'kind')]);
        $ofLea = $this->statementOf($lea);
        $this->assertSame(['Relevé de cours', 'Léa DURAND', '65.00', 2], [$ofLea->title(), $ofLea->owner_name, $ofLea->total, count($ofLea->lines)]);
        $this->assertSame([$ofPaul->id, $ofLea->id, $ofLea->id, null], LessonCharge::orderBy('id')->pluck('boarding_statement_id')->all());

        // Brouillons recalculés : les mêmes cours, sans doublon.
        $this->actingAs($manager)->post(route('boarding.generate'), ['period' => '2026-10']);
        $this->assertSame(2, BoardingStatement::count());
        [$ofPaul, $ofLea] = [$this->statementOf($paul), $this->statementOf($lea)];
        $this->assertSame([$ofPaul->id, $ofLea->id, $ofLea->id, null], LessonCharge::orderBy('id')->pluck('boarding_statement_id')->all());
        $this->actingAs($manager)->get(route('boarding.index', ['mois' => '2026-10']))->assertSee('Relevé de cours · N° '.$ofLea->number)->assertSee('facturé');
        $this->actingAs($manager)->get(route('boarding.statement', [$ofLea, 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Brouillon : Léa ne le voit pas encore dans ses frais de cours.
        $this->fresh();
        $this->actingAs($lea)->get(route('lesson-costs.index'))->assertOk()->assertDontSee('data-costs-statements', false)->assertDontSee('relevé n°');

        // Envoi : la pension va dans le budget de Paul, le cours y est déjà (pas compté deux fois) ; Léa est prévenue, sans budget.
        $this->actingAs($manager)->post(route('boarding.send', $ofPaul))->assertSessionHas('success');
        $this->actingAs($manager)->post(route('boarding.send', $ofLea))->assertSessionHas('success');
        $this->assertSame(420.0, (float) Expense::where('boarding_statement_id', $ofPaul->id)->sum('amount'));
        $this->assertSame(460.0, (float) Expense::where('horse_id', $horse->id)->sum('amount'), 'Pension + cours de Paul, une seule fois.');
        $this->assertSame(0, Expense::where('boarding_statement_id', $ofLea->id)->count());
        $this->assertContains('Relevé de cours – Octobre 2026', $lea->notifications()->get()->pluck('data.title')->all());
        $this->assertContains('Relevé de pension et de cours – Octobre 2026', $paul->notifications()->get()->pluck('data.title')->all());

        // Paiement du relevé de Paul : pension et cours payés, jusque dans son budget.
        $this->actingAs($manager)->post(route('boarding.pay', $ofPaul), ['paid_on' => '2026-10-17'])->assertSessionHas('success');
        $charge = LessonCharge::where('payer_id', $paul->id)->sole();
        $this->assertSame('2026-10-17', $charge->paid_on->toDateString());
        $this->assertSame(0, Expense::where('horse_id', $horse->id)->whereNull('paid_on')->count(), 'Pension et cours de Paul payés.');
        $this->assertSame(2, LessonCharge::where('payer_id', $lea->id)->whereNull('paid_on')->count(), 'Le relevé de Léa reste à payer.');

        // Côté cavalier : relevés reçus, cours de l'année par enseignant, numéro du relevé sur chaque cours.
        $this->fresh();
        $this->actingAs($lea)->get(route('lesson-costs.index'))->assertOk()->assertSee('data-costs-statements', false)->assertSee('Relevé de cours · Octobre 2026')
            ->assertSee('Écurie des Pins · N° '.$ofLea->number)->assertSee('data-costs-coaches', false)->assertSee('Mes cours en 2026')->assertSee('2 cours · dernier le 10 octobre')
            ->assertSee('65,00 €')->assertSee('relevé n° '.$ofLea->number);
        $this->actingAs($lea)->get(route('boarding.statement', $ofLea))->assertOk()->assertSee('Relevé de cours · Octobre 2026')->assertSee(route('lesson-costs.index'), false);
        $this->actingAs($lea)->get(route('boarding.statement', $ofPaul))->assertNotFound();
        $this->fresh();
        $this->actingAs($paul)->get(route('lesson-costs.index'))->assertSee('relevé n° '.$ofPaul->number)->assertSee('Payé');
    }

    public function test_lessons_of_other_instructors_stay_off_the_statement(): void
    {
        [$manager, , $paul, $horse] = $this->setUpStable();
        // Enseignant extérieur, dans l'espace de Paul : ses cours ne regardent pas l'écurie.
        $sophie = Professional::forceCreate(['organization_id' => $paul->personalOrganization()->id, 'first_name' => 'Sophie', 'last_name' => 'LOIR', 'kind' => 'instructor']);
        $sophie->rates()->create(['kind' => 'private_lesson', 'unit' => 'session', 'amount' => 50]);
        $this->fresh();
        $this->actingAs($paul)->post(route('sessions.store'), ['horse_id' => $horse->id, 'scheduled_at' => '2026-10-07 10:00', 'session_type' => 'private_lesson',
            'planned_minutes' => 45, 'instructor' => 'Sophie LOIR'])->assertSessionHasNoErrors();
        $this->assertSame('50.00', LessonCharge::sole()->amount);

        $this->actingAs($manager)->get(route('boarding.index', ['mois' => '2026-10']))->assertDontSee('Sophie LOIR');
        $this->actingAs($manager)->post(route('boarding.generate'), ['period' => '2026-10']);
        $ofPaul = $this->statementOf($paul);
        $this->assertSame(['Relevé de pension', '420.00'], [$ofPaul->title(), $ofPaul->total]);
        $this->assertNull(LessonCharge::sole()->boarding_statement_id);
    }
}
