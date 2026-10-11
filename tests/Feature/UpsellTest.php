<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Services\Entitlements;
use App\Services\OrganizationService;
use App\Services\Upsell;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Propositions d'offre supérieure en Découverte (offre gratuite par défaut). */
class UpsellTest extends TestCase
{
    public function test_free_plan_shows_upgrade_on_dashboard_and_menu_with_trial(): void
    {
        $user = $this->user();
        $this->horseFor($user);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('data-upsell="upgrade"', false)
            ->assertSee('data-upsell="menu"', false)
            ->assertSee('Débloquez tout Jack&amp;Cie', false)
            ->assertSee('Essayer 14 jours gratuitement')
            ->assertSee('/abonnement#offres', false);
    }

    public function test_paid_plan_shows_no_upsell(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('data-upsell="upgrade"', false)->assertDontSee('data-upsell="menu"', false);
        $this->actingAs($user)->get('/budget')->assertOk()->assertDontSee('data-upsell="budget"', false)->assertSee('onclick="document.getElementById(\'new-expense\').showModal()"', false);
        $this->actingAs($user)->get("/chevaux/{$horse->id}/documents")->assertOk()->assertDontSee('data-upsell="documents"', false)->assertDontSee('(offre supérieure)')->assertSee('Ajouter un document');
    }

    public function test_locked_features_show_their_offer_instead_of_a_form_that_fails(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);

        // Budget : proposition, pas de formulaire de dépense voué à l'échec.
        $this->actingAs($user)->get('/budget')->assertOk()
            ->assertSee('data-upsell="budget"', false)->assertSee('Suivez ce que coûte votre cheval')
            ->assertSee('/abonnement?fonction=budget#offres', false)
            ->assertDontSee('onclick="document.getElementById(\'new-expense\').showModal()"', false);

        // Documents : proposition à la place du formulaire d'envoi ; onglets signalés.
        $this->actingAs($user)->get("/chevaux/{$horse->id}/documents")->assertOk()
            ->assertSee('data-upsell="documents"', false)->assertDontSee('Ajouter un document')
            ->assertSee('(offre supérieure)');

        // Envoi forcé malgré tout : refusé, avec la proposition dans le message.
        $this->actingAs($user)->from("/chevaux/{$horse->id}/documents")
            ->post("/chevaux/{$horse->id}/documents", ['file' => UploadedFile::fake()->create('vaccins.pdf', 10, 'application/pdf'), 'category' => 'report'])
            ->assertRedirect()->assertSessionHas('upsell');
        $this->actingAs($user)->withSession(['upsell' => ['organization' => $horse->organization_id, 'key' => 'documents']])
            ->get('/dashboard')->assertSee('data-upsell="documents"', false);
    }

    public function test_assistant_status_offers_upgrade_only_when_the_assistant_really_works(): void
    {
        $user = $this->user();
        $this->horseFor($user);

        config(['services.anthropic.key' => null]);
        $this->actingAs($user)->getJson('/assistant/etat')->assertOk()->assertJsonPath('available', false)->assertJsonPath('upsell', null);

        config(['services.anthropic.key' => 'sk-ant-test']);
        $this->actingAs($user)->getJson('/assistant/etat')->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('upsell.plan', 'Particulier')
            ->assertJsonPath('upsell.trial', true)
            ->assertJsonPath('upsell.cta', 'Essayer 14 jours gratuitement');
    }

    public function test_horse_limit_reached_offers_a_bigger_plan(): void
    {
        $user = $this->user();
        $this->horseFor($user);

        $this->actingAs($user)->get('/chevaux/nouveau')->assertOk()
            ->assertSee('data-upsell="max_horses"', false)->assertSee('Un cheval de plus ?')
            ->assertDontSee('name="official_name"', false);
        $this->actingAs($user)->get('/chevaux')->assertOk()->assertSee('data-upsell="max_horses"', false);
    }

    public function test_billing_page_highlights_plans_that_unlock_the_feature(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/abonnement?fonction=budget')->assertOk()
            ->assertSee('id="offres"', false)
            ->assertSee('Suivez ce que coûte votre cheval')
            ->assertSee('data-unlocks="budget"', false)
            ->assertSee('Nouveau pour vous')
            ->assertSee('Essayer 14 jours gratuitement');
        // Paramètre inconnu : ignoré.
        $this->actingAs($user)->get('/abonnement?fonction=<script>')->assertOk()->assertDontSee('data-unlocks', false);
    }

    public function test_no_trial_promised_after_a_previous_subscription_and_no_button_without_billing_rights(): void
    {
        $owner = $this->user();
        $org = $owner->personalOrganization();
        License::create(['organization_id' => $org->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'particulier')->value('id'),
            'source' => 'stripe', 'status' => 'expired', 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonth()]);

        // Essai déjà utilisé : on ne promet plus d'essai gratuit.
        $offer = app(Upsell::class)->for($org, $owner, 'max_horses');
        $this->assertSame(0, $offer['trial_days']);
        $this->assertSame('Passer à l\'offre Propriétaire multi-chevaux', $offer['cta']);
        $this->assertTrue($offer['can_upgrade']);

        // Membre sans droit sur l'abonnement : information seulement, pas de bouton.
        $member = $this->user();
        $stable = $this->stable($owner);
        License::where('organization_id', $stable->id)->delete();
        app(Entitlements::class)->flush();
        app(OrganizationService::class)->addMember($stable, $member, 'rider');
        $memberOffer = app(Upsell::class)->for($stable->fresh(), $member->fresh(), 'budget');
        $this->assertNotNull($memberOffer);
        $this->assertFalse($memberOffer['can_upgrade']);
        $this->assertStringContainsString('le responsable de l\'espace', $memberOffer['note']);
        $this->assertNull(app(Upsell::class)->upgrade($stable->fresh(), $member->fresh()));
    }

    public function test_exports_are_reserved_to_paid_plans_except_personal_data(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);

        // Découverte : export refusé, retour sur la page avec la proposition.
        foreach (["/chevaux/{$horse->id}/export/fiche.pdf", "/chevaux/{$horse->id}/export/sante.pdf", "/chevaux/{$horse->id}/export/seances.csv", '/budget/export.csv'] as $url) {
            $this->actingAs($user)->from("/chevaux/{$horse->id}")->get($url)->assertRedirect("/chevaux/{$horse->id}")->assertSessionHas('upsell', ['organization' => $horse->organization_id, 'key' => 'exports']);
        }
        $this->actingAs($user)->get("/chevaux/{$horse->id}")->assertSee('(offre supérieure)');
        $this->actingAs($user)->withSession(['upsell' => ['organization' => $horse->organization_id, 'key' => 'exports']])
            ->get('/dashboard')->assertSee('data-upsell="exports"', false)->assertSee('Exportez en PDF et CSV');

        // Export des données personnelles (RGPD) : toujours possible.
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/parametres/donnees/export')->assertOk();

        // Offre payante : exports disponibles, y compris en lecture seule après la fin de l'abonnement.
        $license = $this->grantLicense($user->personalOrganization(), 'particulier');
        app(Entitlements::class)->flush();
        $this->actingAs($user)->get("/chevaux/{$horse->id}/export/fiche.pdf")->assertOk();
        $this->actingAs($user)->get("/chevaux/{$horse->id}")->assertDontSee('(offre supérieure)');
        $license->update(['status' => 'expired', 'ends_at' => now()->subDay()]);
        app(Entitlements::class)->flush();
        $this->actingAs($user)->get("/chevaux/{$horse->id}/export/seances.csv")->assertOk();
    }
}
