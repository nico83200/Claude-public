<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\HorseOrganizationAssignment;
use App\Notifications\AppNotification;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Bloc « à faire maintenant » de l'analyse du 15/10/2026 : file d'attente, cache des réglages. */
class TechnicalHardeningTest extends TestCase
{
    public function test_notifications_go_through_the_queue_except_urgent_ones(): void
    {
        Queue::fake();
        $user = $this->user();

        // Notification ordinaire : mise en file, rien n'est envoyé pendant la requête.
        $user->notify(new AppNotification('announcement', 'Message', 'Bonjour'));
        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof AppNotification && $job->notification->kind === 'announcement');
        $this->assertSame(0, $user->notifications()->count());

        // Alerte technique : envoi immédiat (la file dépend du cron qu'elle signale peut-être comme arrêté).
        $admin = $this->user();
        $admin->notifyNow(new AppNotification('system_alert', 'Alerte', 'Cron arrêté'));
        $this->assertSame(1, $admin->notifications()->count());
        Queue::assertNotPushed(SendQueuedNotifications::class, fn ($job) => $job->notification->kind === 'system_alert');
    }

    public function test_page_queries_do_not_grow_with_the_number_of_horses(): void
    {
        $manager = $this->user();
        $stable = $this->stable($manager);
        $this->horseFor($manager, $stable);
        // Cheval d'un propriétaire pris en charge par l'écurie (droits via rattachement).
        $owner = $this->user();
        $boarded = $this->horseFor($owner);
        HorseOrganizationAssignment::create(['horse_id' => $boarded->id, 'organization_id' => $stable->id, 'status' => 'active', 'starts_at' => now()->subDay(),
            'permissions' => [Perm::HORSE_VIEW, Perm::HEALTH_VIEW, Perm::CALENDAR_VIEW], 'requested_by' => $owner->id]);
        $count = function (string $url) use ($manager) {
            app(HorseAccess::class)->flush();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($manager->fresh())->get($url)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $pages = ['/dashboard', '/chevaux', '/calendrier', '/budget', '/seances'];
        array_map($count, $pages); // caches de réglages remplis
        $before = array_map($count, $pages);

        foreach (range(1, 4) as $i) {
            $this->horseFor($manager, $stable);
            $other = $this->horseFor($otherOwner = $this->user());
            HorseOrganizationAssignment::create(['horse_id' => $other->id, 'organization_id' => $stable->id, 'status' => 'active', 'starts_at' => now()->subDay(),
                'permissions' => [Perm::HORSE_VIEW, Perm::HEALTH_VIEW, Perm::CALENDAR_VIEW], 'requested_by' => $otherOwner->id]);
        }

        $this->assertSame(array_combine($pages, $before), array_combine($pages, array_map($count, $pages)));
    }

    public function test_a_missing_setting_is_cached_instead_of_read_on_every_call(): void
    {
        ApplicationSetting::get('jamais_defini');
        DB::enableQueryLog();
        $this->assertSame('défaut', ApplicationSetting::get('jamais_defini', 'défaut'));
        $this->assertNull(ApplicationSetting::get('jamais_defini'));
        $this->assertSame([], DB::getQueryLog());

        // Une valeur enregistrée remplace bien l'absence mémorisée.
        ApplicationSetting::put('jamais_defini', 'oui');
        $this->assertSame('oui', ApplicationSetting::get('jamais_defini', 'défaut'));
    }

    public function test_legal_pages_show_the_publisher_and_the_real_subprocessors(): void
    {
        config(['services.stripe.secret' => 'sk_test', 'services.anthropic.key' => 'sk-ant', 'services.elevenlabs.key' => null, 'services.google.client_id' => 'g-id', 'services.apple.client_id' => null]);

        $privacy = $this->get('/confidentialite')->assertOk();
        $privacy->assertSee('Nicolas Lefèvre, entrepreneur individuel (EI)')->assertSee('SIRET 519 791 719 00114')->assertSee('83130 La Garde')
            ->assertSee('Hostinger International Ltd.')->assertSee('Stripe Payments Europe')->assertSee('Anthropic PBC')->assertSee('Google LLC')
            ->assertDontSee('ElevenLabs')->assertDontSee('à compléter')->assertDontSee('Modèle à faire valider')->assertDontSee('texte type');
        $this->get('/conditions')->assertOk()->assertSee('Éditeur')->assertSee('TVA non applicable')->assertDontSee('Modèle à faire valider');

        // Ancien texte d'exemple resté dans l'administration : ignoré au profit de la liste réelle.
        ApplicationSetting::put('subprocessors', "Hébergement : [à compléter]\nPaiement : Stripe");
        $this->get('/confidentialite')->assertSee('Anthropic PBC')->assertDontSee('à compléter');
        // Texte complet saisi par l'administrateur : il prime.
        ApplicationSetting::put('subprocessors', "Hébergeur X\nPrestataire Y");
        $this->get('/confidentialite')->assertSee('Prestataire Y')->assertDontSee('Anthropic PBC');

        // Rappel « texte type » réservé aux super-administrateurs.
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->get('/confidentialite')->assertSee('texte type');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/parametres')->assertOk()->assertSee('Laisser vide pour la liste automatique');
    }
}
