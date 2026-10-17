<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Horse;
use App\Models\LessonCharge;
use App\Models\LessonPackage;
use App\Models\Professional;
use App\Models\RidingSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Cartes de cours, forfaits mensuels et annulation tardive. */
class Version244RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setUpOwner(): array
    {
        Carbon::setTestNow('2026-10-17 18:00');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $jimmy = Professional::forceCreate(['organization_id' => $owner->personalOrganization()->id, 'first_name' => 'Jimmy', 'last_name' => 'BLANC', 'kind' => 'instructor']);
        $jimmy->rates()->create(['kind' => 'private_lesson', 'unit' => 'session', 'duration_minutes' => 45, 'amount' => 40]);
        $jimmy->rates()->create(['kind' => 'travel', 'unit' => 'visit', 'amount' => 15]);

        return [$owner, $horse, $jimmy];
    }

    private function lesson(User $by, Horse $horse, string $at): void
    {
        $this->actingAs($by)->post(route('sessions.store'), ['horse_id' => $horse->id, 'scheduled_at' => $at, 'session_type' => 'private_lesson', 'planned_minutes' => 45, 'instructor' => 'Jimmy BLANC']);
    }

    private function package(User $by, Horse $horse, Professional $jimmy, array $data)
    {
        return $this->actingAs($by)->post(route('lesson-packages.store'), $data + ['professional_id' => $jimmy->id, 'horse_id' => $horse->id]);
    }

    private function lessonCharges()
    {
        return LessonCharge::whereIn('kind', ['private_lesson', 'group_lesson'])->orderBy('charged_on')->orderBy('id')->get();
    }

    public function test_card_counts_lessons_then_falls_back_to_the_rate(): void
    {
        [$owner, $horse, $jimmy] = $this->setUpOwner();
        $this->package($owner, $horse, $jimmy, ['kind' => 'card', 'lessons' => 3, 'price' => '110', 'starts_on' => '2026-10-01'])->assertSessionHas('success');
        $fee = LessonCharge::where('kind', 'card')->sole();
        $this->assertSame(['110.00', 'Carte de 3 cours avec Jimmy BLANC', '2026-10-01'], [$fee->amount, $fee->label, $fee->charged_on->toDateString()]);
        $this->assertNotNull($fee->expense_id, 'Prix de la carte : dans le budget du propriétaire.');

        foreach (['2026-10-13 10:00', '2026-10-14 10:00', '2026-10-15 10:00', '2026-10-16 10:00'] as $at) {
            $this->lesson($owner, $horse, $at);
        }
        $lessons = $this->lessonCharges();
        $this->assertSame(['0.00', '0.00', '0.00', '40.00'], $lessons->pluck('amount')->all(), 'Trois cours décomptés, le quatrième au tarif.');
        $this->assertStringEndsWith('· carte (1/3)', $lessons[0]->label);
        $this->assertStringEndsWith('· carte (3/3)', $lessons[2]->label);
        $this->assertNull($lessons[0]->expense_id, 'Pas de dépense à 0 € dans le budget.');
        $this->assertSame(4, LessonCharge::where('kind', 'travel')->count(), 'Le déplacement reste compté chaque jour.');
        $titles = $owner->notifications()->get()->pluck('data.title')->all();
        $this->assertContains('Carte de cours : plus que 2 cours', $titles);
        $this->assertContains('Carte de cours utilisée', $titles);

        // Page des frais : la carte et son décompte.
        $this->actingAs($owner)->get(route('lesson-costs.index'))->assertOk()->assertSee('data-packages', false)->assertSee('3 / 3 cours utilisés');
    }

    public function test_card_added_afterwards_and_removed(): void
    {
        [$owner, $horse, $jimmy] = $this->setUpOwner();
        $this->lesson($owner, $horse, '2026-10-12 10:00');
        $this->lesson($owner, $horse, '2026-10-14 10:00');
        $paid = $this->lessonCharges()->first();
        $paid->update(['paid_on' => '2026-10-12']);
        // Carte achetée le 10 : le cours non payé du 14 y est décompté, celui du 12 (déjà payé) ne bouge pas.
        $this->package($owner, $horse, $jimmy, ['kind' => 'card', 'lessons' => 10, 'price' => '380', 'starts_on' => '2026-10-10']);
        $this->assertSame(['40.00', '0.00'], $this->lessonCharges()->pluck('amount')->all());

        // Carte supprimée : le cours redevient compté au tarif, le prix de la carte disparaît.
        $package = LessonPackage::sole();
        $this->actingAs($owner)->delete(route('lesson-packages.destroy', $package))->assertSessionHas('success');
        $this->assertSame(['40.00', '40.00'], $this->lessonCharges()->pluck('amount')->all());
        $this->assertSame(0, LessonCharge::where('kind', 'card')->count());
        $this->assertSame(0.0, (float) Expense::where('horse_id', $horse->id)->where('comment', 'like', 'Carte%')->sum('amount'));
        // Seule la personne qui paie gère ses cartes.
        $other = $this->user();
        $this->package($owner, $horse, $jimmy, ['kind' => 'card', 'lessons' => 5, 'price' => '200', 'starts_on' => '2026-10-10']);
        $this->actingAs($other)->delete(route('lesson-packages.destroy', LessonPackage::latest('id')->first()))->assertForbidden();
    }

    public function test_monthly_package_counts_each_month(): void
    {
        [$owner, $horse, $jimmy] = $this->setUpOwner();
        $this->package($owner, $horse, $jimmy, ['kind' => 'monthly', 'lessons' => 2, 'price' => '70', 'starts_on' => '2026-10-01']);
        $this->assertSame(['Forfait d\'octobre 2026 (2 cours) avec Jimmy BLANC'], LessonCharge::where('kind', 'monthly')->pluck('label')->all());
        foreach (['2026-10-05 10:00', '2026-10-06 10:00', '2026-10-07 10:00'] as $at) {
            $this->lesson($owner, $horse, $at);
        }
        $this->assertSame(['0.00', '0.00', '40.00'], $this->lessonCharges()->pluck('amount')->all());
        $this->assertStringEndsWith('· forfait (2/2 en octobre)', $this->lessonCharges()[1]->label);

        // Nouveau mois : la tâche quotidienne compte le forfait de novembre et prévient.
        Carbon::setTestNow('2026-11-02 08:00');
        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame(2, LessonCharge::where('kind', 'monthly')->count());
        $this->assertTrue($owner->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Forfait de cours : novembre'));
        $this->artisan('reminders:send');
        $this->assertSame(2, LessonCharge::where('kind', 'monthly')->count(), 'Une seule fois par mois.');
        // Un cours de novembre est de nouveau compris.
        $this->lesson($owner, $horse, '2026-11-02 07:00');
        $this->assertSame('0.00', $this->lessonCharges()->last()->amount);
    }

    public function test_late_cancellation_keeps_the_lesson_due(): void
    {
        [$owner, $horse, $jimmy] = $this->setUpOwner();
        $this->actingAs($owner)->put(route('professionals.cancel-notice', $jimmy), ['cancel_notice_hours' => 24])->assertSessionHas('success');
        $this->actingAs($owner)->get(route('professionals.show', $jimmy))->assertSee('Annulation moins de 24 h avant : cours dû.');
        $this->lesson($owner, $horse, '2026-10-18 10:00'); // dans 16 h
        $this->lesson($owner, $horse, '2026-10-20 10:00'); // dans 3 jours
        [$soon, $later] = RidingSession::orderBy('scheduled_at')->get()->all();
        $this->assertSame(0, LessonCharge::count(), 'Cours prévus : rien de compté.');

        $this->actingAs($owner)->post(route('sessions.cancel', $soon));
        $charge = LessonCharge::sole();
        $this->assertSame(['40.00', 'Cours particulier 45 min avec Jimmy BLANC · annulé tardivement'], [$charge->amount, $charge->label]);
        $this->assertSame(0, LessonCharge::where('kind', 'travel')->count(), 'Pas de déplacement pour un cours annulé.');
        $this->actingAs($owner)->post(route('sessions.cancel', $later));
        $this->assertSame(1, LessonCharge::count(), 'Annulé assez tôt : rien de dû.');
        // Sans règle d'annulation, rien n'est dû.
        $this->actingAs($owner)->put(route('professionals.cancel-notice', $jimmy), ['cancel_notice_hours' => '']);
        $this->assertNull($jimmy->fresh()->cancel_notice_hours);
    }
}
