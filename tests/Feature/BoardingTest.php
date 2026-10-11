<?php

namespace Tests\Feature;

use App\Models\BoardingCharge;
use App\Models\BoardingContract;
use App\Models\BoardingStatement;
use App\Models\Expense;
use App\Models\HorseOrganizationAssignment;
use App\Notifications\AppNotification;
use App\Services\OrganizationService;
use App\Support\Perm;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Pension facturée aux propriétaires : prorata, prestations, relevé, envoi, paiement. */
class BoardingTest extends TestCase
{
    private function setUpStable(): array
    {
        $manager = $this->user();
        $stable = $this->stable($manager, 'Écurie des Pins');
        $owner = $this->user(['name' => 'Claire Dupont', 'email' => 'claire@exemple.fr']);
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNI DU BOIS', 'usual_name' => 'Uni']);
        HorseOrganizationAssignment::create(['horse_id' => $horse->id, 'organization_id' => $stable->id, 'kind' => 'boarding', 'permissions' => Perm::boardingPreset(), 'status' => 'active', 'starts_at' => now()->subYear()]);
        $manager->forceFill(['current_organization_id' => $stable->id])->save();

        return [$manager->fresh(), $stable, $owner, $horse];
    }

    public function test_monthly_statement_with_prorata_charges_send_and_payment(): void
    {
        Notification::fake();
        [$manager, $stable, $owner, $horse] = $this->setUpStable();

        // Le propriétaire (compte Jack&Cie) est proposé d'après l'espace d'origine du cheval.
        $this->actingAs($manager)->get('/ecurie/pension')->assertOk()->assertSee('Pensionnaires')->assertSee('claire@exemple.fr');
        $this->actingAs($manager)->post('/ecurie/pension/contrats', ['horse_id' => $horse->id, 'owner_name' => 'Claire Dupont', 'owner_email' => 'claire@exemple.fr',
            'label' => 'Pension box + paddock', 'monthly_amount' => '420', 'starts_on' => '2026-09-16'])->assertSessionHas('success');
        $this->assertSame($owner->id, BoardingContract::first()->owner_user_id);
        $this->actingAs($manager)->post('/ecurie/pension/prestations', ['horse_id' => $horse->id, 'charged_on' => '2026-09-20', 'label' => 'Sortie au paddock', 'quantity' => '4', 'unit_price' => '5'])->assertSessionHas('success');
        $this->actingAs($manager)->post('/ecurie/pension/prestations', ['horse_id' => $horse->id, 'charged_on' => '2026-10-02', 'label' => 'Complément', 'quantity' => '1', 'unit_price' => '30']);

        // Septembre : 15 jours sur 30 → 210 € + 4 × 5 € de paddock.
        $this->actingAs($manager)->post('/ecurie/pension/releves', ['period' => '2026-09'])->assertSessionHas('success');
        $s = BoardingStatement::firstOrFail();
        $this->assertSame('P202609-001', $s->number);
        $this->assertSame('230.00', $s->total);
        $this->assertSame('15 jours sur 30 (16/09 – 30/09)', $s->lines[0]['detail']);
        $this->assertSame($s->id, BoardingCharge::where('label', 'Sortie au paddock')->value('statement_id'));
        $this->assertNull(BoardingCharge::where('label', 'Complément')->value('statement_id'));

        // Recalcul d'un brouillon : pas de doublon.
        $this->actingAs($manager)->post('/ecurie/pension/releves', ['period' => '2026-09']);
        $this->assertSame(1, BoardingStatement::count());
        $s = BoardingStatement::firstOrFail();

        $this->actingAs($manager)->get("/releves-pension/{$s->id}?format=pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($manager)->post("/ecurie/pension/releves/{$s->id}/envoyer")->assertSessionHas('success');
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => str_contains($n->title, 'Septembre 2026') && str_contains($n->body, '230,00 €'));
        $expense = Expense::where('boarding_statement_id', $s->id)->firstOrFail();
        $this->assertSame($horse->organization_id, $expense->organization_id);
        $this->assertSame('230.00', $expense->amount);
        $this->assertNull($expense->paid_on);

        // Le propriétaire voit son relevé ; un tiers non.
        $this->actingAs($owner)->get("/releves-pension/{$s->id}")->assertOk()->assertSee('230,00 €')->assertSee('Sortie au paddock');
        $this->actingAs($this->user())->get("/releves-pension/{$s->id}")->assertNotFound();

        // Envoyé : figé (pas recalculé), puis payé → la dépense du propriétaire passe à payée.
        $this->actingAs($manager)->post('/ecurie/pension/releves', ['period' => '2026-09'])->assertSessionHas('warning');
        $this->actingAs($manager)->post("/ecurie/pension/releves/{$s->id}/paiement", ['paid_on' => '2026-10-05'])->assertSessionHas('success');
        $this->assertSame('paid', $s->fresh()->status);
        $this->assertSame('2026-10-05', $expense->fresh()->paid_on->toDateString());
    }

    public function test_reserved_to_stable_managers(): void
    {
        [$manager, $stable, $owner] = $this->setUpStable();
        $this->actingAs($owner)->get('/ecurie/pension')->assertNotFound(); // espace personnel
        $rider = $this->user();
        app(OrganizationService::class)->addMember($stable, $rider, 'rider');
        $rider->forceFill(['current_organization_id' => $stable->id])->save();
        $this->actingAs($rider->fresh())->get('/ecurie/pension')->assertForbidden();
    }
}
