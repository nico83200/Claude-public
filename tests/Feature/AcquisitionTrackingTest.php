<?php

namespace Tests\Feature;

use App\Models\AcquisitionVisit;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Provenance des inscriptions : QR code des affiches, lien Instagram, conversions visibles par le super-administrateur. */
class AcquisitionTrackingTest extends TestCase
{
    private function newVisitor(): void
    {
        auth()->logout();
        session()->flush();
        session()->setId(Str::random(40));
    }

    public function test_scans_clicks_signups_and_redemptions_are_counted_by_source(): void
    {
        $promo = PromoCode::create(['code' => 'EQUITA2026', 'type' => 'free_access', 'label' => 'Salon', 'is_active' => true,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'particulier')->value('id'), 'free_days' => 30]);

        // Affiche : même téléphone qui scanne deux fois, puis s'inscrit avec le code.
        $this->get('/register?code=EQUITA2026&src=affiche')->assertOk();
        $this->get('/register?code=EQUITA2026&src=affiche')->assertOk();
        $this->post('/register', ['name' => 'Lou', 'email' => 'lou@exemple.fr', 'password' => 'Cheval2026secret', 'password_confirmation' => 'Cheval2026secret', 'terms' => '1', 'promo_code' => 'EQUITA2026'])->assertRedirect();

        // Deux autres scans d'affiche sans inscription, et un visiteur venu d'Instagram.
        $this->newVisitor();
        $this->get('/register?code=EQUITA2026&src=affiche');
        $this->newVisitor();
        $this->get('/register?code=EQUITA2026&src=instagram');
        // Sans paramètre : rien n'est enregistré ; source nettoyée.
        $this->newVisitor();
        $this->get('/register');
        $this->get('/register?src=<script>Flyer Club</script>');

        $affiche = AcquisitionVisit::where('source', 'affiche')->get();
        $this->assertCount(2, $affiche);
        $this->assertSame(3, (int) $affiche->sum('hits'));
        $lou = User::where('email', 'lou@exemple.fr')->firstOrFail();
        $this->assertDatabaseHas('acquisition_visits', ['source' => 'affiche', 'code' => 'EQUITA2026', 'user_id' => $lou->id]);
        $this->assertNotNull(AcquisitionVisit::where('user_id', $lou->id)->value('code_redeemed_at'));
        $this->assertSame(1, AcquisitionVisit::where('source', 'instagram')->count());
        $this->assertSame(['affiche', 'instagram', 'script-flyer-club-script'], AcquisitionVisit::orderBy('source')->pluck('source')->unique()->values()->all());

        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $report = app(\App\Services\AcquisitionTracker::class)->report(null, 'EQUITA2026')->keyBy('source');
        $this->assertSame([3, 2, 1, 1], [$report['affiche']->hits, $report['affiche']->visitors, $report['affiche']->accounts, $report['affiche']->redeemed]);
        $this->assertSame([1, 1, 0], [$report['instagram']->hits, $report['instagram']->visitors, $report['instagram']->accounts]);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/statistiques')->assertOk()
            ->assertSee('Provenance des inscriptions')->assertSee('Affiches (QR code)')->assertSee('Instagram')->assertDontSee('lou@exemple.fr');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/codes/'.$promo->id)->assertOk()
            ->assertSee('data-acquisition', false)->assertSee('?code=EQUITA2026&amp;src=instagram', false);
        $this->actingAs($this->user())->get('/admin/statistiques')->assertNotFound();
    }

    public function test_code_and_source_survive_installing_the_app_after_a_scan(): void
    {
        PromoCode::create(['code' => 'EQUITA2026', 'type' => 'free_access', 'label' => 'Salon', 'is_active' => true,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'particulier')->value('id'), 'free_days' => 30]);

        // Sans scan : l'application s'ouvre sur l'accueil.
        $this->get('/app.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->assertJsonPath('start_url', '/dashboard?source=pwa');

        // Scan de l'affiche sur un téléphone sans l'application : page d'installation, puis installation.
        $this->get('/register?code=EQUITA2026&src=affiche')->assertOk();
        $visit = AcquisitionVisit::sole();
        $start = $this->get('/app.webmanifest')->assertOk()->json('start_url');
        $this->assertStringStartsWith('/register?', $start);
        $this->assertStringContainsString('code=EQUITA2026', $start);

        // L'application installée a sa propre session (iPhone) : le code et la source sont retrouvés, sans double comptage.
        $this->newVisitor();
        $this->get($start)->assertOk()->assertSee('data-signup-banner', false)->assertSee('EQUITA2026');
        $this->assertSame(1, AcquisitionVisit::count());
        $this->assertSame(1, (int) $visit->fresh()->hits);
        $this->post('/register', ['name' => 'Lou', 'email' => 'lou@exemple.fr', 'password' => 'Cheval2026secret', 'password_confirmation' => 'Cheval2026secret', 'terms' => '1', 'promo_code' => 'EQUITA2026'])->assertRedirect();
        $lou = User::where('email', 'lou@exemple.fr')->firstOrFail();
        $this->assertSame([$lou->id, 'affiche'], [$visit->fresh()->user_id, $visit->fresh()->source]);
        $this->assertNotNull($visit->fresh()->code_redeemed_at);

        // Jeton falsifié : ignoré (nouvelle visite normale, aucune reprise).
        $this->newVisitor();
        $this->get('/register?suite='.$visit->id.'.faux&src=affiche')->assertOk();
        $this->assertSame(2, AcquisitionVisit::count());
        // Une fois connecté, le manifeste redevient celui de l'accueil.
        $this->actingAs($lou)->get('/app.webmanifest')->assertJsonPath('start_url', '/dashboard?source=pwa');
    }
}
