<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeatureFlag;
use App\Models\HorseAccessGrant;
use App\Models\Professional;
use App\Models\PushSubscription;
use App\Models\Treatment;
use App\Models\UserProfile;
use App\Notifications\AppNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Récapitulatif de la semaine, fiche d'urgence (QR code) et démarrage guidé. */
class WeeklyDigestEmergencyOnboardingTest extends TestCase
{
    public function test_sunday_digest_lists_the_week_and_is_sent_once(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-18 18:05')); // dimanche
        $user = $this->user(['name' => 'Léa Martin']);
        $user->forceFill(['last_seen_at' => now()->subDay()])->save();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user, null, ['usual_name' => 'Jack']);

        CalendarEvent::forceCreate(['organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Maréchal-ferrant', 'starts_at' => Carbon::parse('2026-10-20 09:00'), 'status' => 'planned', 'created_by' => $user->id]);
        CalendarEvent::forceCreate(['organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'type' => 'custom', 'title' => 'Trop loin', 'starts_at' => Carbon::parse('2026-11-05 09:00'), 'status' => 'planned', 'created_by' => $user->id]);
        Treatment::forceCreate(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'horse_id' => $horse->id, 'product' => 'Equipalazone', 'starts_on' => '2026-10-15', 'ends_on' => '2026-10-22', 'status' => 'active']);
        Expense::forceCreate(['organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::where('key', 'boarding')->value('id'), 'spent_on' => '2026-10-21', 'amount' => 380, 'comment' => 'Pension novembre', 'author_id' => $user->id]);

        $this->artisan('reminders:weekly')->assertSuccessful();
        Notification::assertSentTo($user, AppNotification::class, function ($n) {
            return $n->kind === 'weekly_digest' && str_contains($n->body, '2 rendez-vous') && str_contains($n->body, '1 traitement')
                && str_contains($n->mailBody, 'Maréchal-ferrant (Jack)') && str_contains($n->mailBody, 'Equipalazone') && str_contains($n->mailBody, 'Pension novembre')
                && ! str_contains($n->mailBody, 'Trop loin') && str_ends_with($n->url, '/ma-semaine');
        });
        $this->assertSame('2026-10-18', $user->profile->fresh()->weekly_digest_sent_on->toDateString());

        // Une seule fois par dimanche ; jamais en semaine ; désactivable.
        Notification::fake();
        $this->artisan('reminders:weekly');
        Notification::assertNothingSent();
        $this->travelTo(Carbon::parse('2026-10-25 18:05'));
        $user->profile->update(['weekly_digest' => false]);
        $this->artisan('reminders:weekly');
        Notification::assertNothingSent();

        // Page « Ma semaine » et réglage.
        $this->travelTo(Carbon::parse('2026-10-19 10:00'));
        $this->actingAs($user)->get('/ma-semaine')->assertOk()->assertSee('data-digest', false)->assertSee('Maréchal-ferrant')->assertSee('Equipalazone')->assertSee('Pension novembre');
        $this->actingAs($user)->get('/parametres/notifications')->assertSee('data-weekly-digest-setting', false);
        $this->actingAs($user)->put('/parametres/notifications', ['weekly_digest' => '1', 'email_notifications' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($user->profile->fresh()->weekly_digest);
    }

    public function test_digest_email_uses_the_detailed_text(): void
    {
        FeatureFlag::where('key', 'email_notifications')->update(['enabled_globally' => true]);
        $user = $this->user();
        $html = (string) (new AppNotification('weekly_digest', 'Votre semaine', 'Résumé court', url('/ma-semaine'), mailBody: "**Au programme**\n• Mardi 20 à 09:00 – Maréchal"))->toMail($user)->render();
        $this->assertStringContainsString('<strong>Au programme</strong>', $html);
        $this->assertStringContainsString('Maréchal', $html);
        $this->assertStringNotContainsString('Résumé court', $html);
    }

    public function test_emergency_sheet_is_public_only_when_enabled(): void
    {
        $owner = $this->user(['name' => 'Paul Durand']);
        $horse = $this->horseFor($owner, null, ['usual_name' => 'Uni', 'precautions' => 'Tape à l\'attache', 'general_notes' => 'Note privée']);
        $vet = Professional::forceCreate(['organization_id' => $horse->organization_id, 'last_name' => 'Vétodoc', 'kind' => 'veterinarian', 'phone' => '0611223344', 'notes' => 'Secret']);
        $horse->professionals()->attach($vet->id, ['is_primary' => true]);
        Treatment::forceCreate(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'horse_id' => $horse->id, 'product' => 'Antibiotique X', 'dosage' => '2 fois par jour', 'starts_on' => now()->subDay()->toDateString(), 'status' => 'active']);

        $this->actingAs($owner)->get(route('horses.emergency.edit', $horse))->assertOk()->assertSee('data-emergency-enable', false);
        $this->actingAs($owner)->put(route('horses.emergency.update', $horse), ['emergency_contact_name' => 'Paul', 'emergency_contact_phone' => '06 00 00 00 01', 'emergency_notes' => 'Allergique à la pénicilline', 'action' => 'enable'])->assertSessionHasNoErrors();
        $token = $horse->fresh()->emergency_token;
        $this->assertSame(40, strlen($token));

        auth()->logout();
        $page = $this->get('/urgence/'.$token)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $page->assertSee('Uni')->assertSee('tel:0600000001', false)->assertSee('Allergique à la pénicilline')->assertSee('Tape à l')
            ->assertSee('Vétodoc')->assertSee('0611223344')->assertSee('Antibiotique X')
            ->assertDontSee('Note privée')->assertDontSee('Secret');

        $this->actingAs($owner)->get(route('horses.emergency.print', $horse))->assertOk()->assertSee('<svg', false);
        $this->actingAs($owner)->get(route('horses.emergency.qr', $horse))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        // Lien changé : l'ancien QR ne marche plus ; désactivée : plus rien.
        $this->actingAs($owner)->put(route('horses.emergency.update', $horse), ['action' => 'renew']);
        $this->get('/urgence/'.$token)->assertNotFound();
        $this->get('/urgence/'.$horse->fresh()->emergency_token)->assertOk();
        $this->actingAs($owner)->put(route('horses.emergency.update', $horse), ['action' => 'disable']);
        $this->assertNull($horse->fresh()->emergency_token);

        // Un cavalier en lecture seule ne peut pas la gérer.
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view']]);
        $this->actingAs($rider)->get(route('horses.emergency.edit', $horse))->assertForbidden();
    }

    public function test_onboarding_checklist_ticks_itself_and_can_be_hidden(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('data-onboarding', false)->assertSee('data-onboarding-step="horse" data-done="0"', false);

        $horse = $this->horseFor($user);
        foreach (['deworming', 'farrier'] as $key) {
            CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => CareCategory::where('key', $key)->value('id'), 'performed_at' => now()->subWeek(), 'author_id' => $user->id]);
        }
        PushSubscription::forceCreate(['user_id' => $user->id, 'endpoint' => 'https://push.example/1', 'endpoint_hash' => hash('sha256', 'https://push.example/1'), 'public_key' => 'k', 'auth_token' => 'a']);
        $html = $this->actingAs($user)->get('/dashboard')->getContent();
        foreach (['horse', 'health', 'notifications'] as $step) {
            $this->assertStringContainsString('data-onboarding-step="'.$step.'" data-done="1"', $html);
        }
        $this->assertStringContainsString('data-onboarding-step="share" data-done="0"', $html);
        $this->assertStringContainsString('3 étapes sur 5', $html);

        $this->actingAs($user)->postJson('/demarrage/installe')->assertOk();
        $this->assertNotNull(UserProfile::where('user_id', $user->id)->value('app_installed_at'));
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $this->user()->id, 'permissions' => ['horse.view']]);
        // Tout est fait : la liste disparaît.
        $user->refresh();
        $this->actingAs($user)->get('/dashboard')->assertDontSee('data-onboarding', false);

        $other = $this->user();
        $this->actingAs($other)->post('/demarrage/masquer')->assertSessionHas('success');
        $this->actingAs($other)->get('/dashboard')->assertDontSee('data-onboarding', false);
    }
}
