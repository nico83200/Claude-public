<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Notifications\AppNotification;
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
}
