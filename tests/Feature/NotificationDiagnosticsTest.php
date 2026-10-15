<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\UserProfile;
use App\Services\NotificationDiagnostics;
use App\Services\WebPush;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Diagnostic des notifications : cron, relais sans cron, appareils, raison du rappel quotidien. */
class NotificationDiagnosticsTest extends TestCase
{
    private function admin()
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    public function test_diagnostic_page_shows_cron_devices_and_daily_reminder_reason(): void
    {
        $admin = $this->admin();
        $user = $this->user();
        $this->horseFor($user);
        UserProfile::updateOrCreate(['user_id' => $user->id], ['daily_reminder_at' => '00:00:00']);
        $as = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        Cache::forget(NotificationDiagnostics::CRON_KEY);
        $as()->get('/admin/notifications?email='.$user->email)->assertOk()
            ->assertSee('Le cron du serveur ne tourne pas')->assertSee('artisan schedule:run')->assertSee(base_path('cron.php'))
            ->assertSee('Aucun appareil abonné')->assertSee('pas encore parti');

        PushSubscription::forceCreate(['user_id' => $user->id, 'endpoint' => 'https://web.push.apple.com/abc', 'endpoint_hash' => hash('sha256', 'abc'), 'public_key' => 'x', 'auth_token' => 'y',
            'content_encoding' => 'aes128gcm', 'device' => 'iPhone', 'last_failure_at' => now(), 'last_error' => 'Refusé (403) : BadJwtToken']);
        NotificationDiagnostics::beat();
        $as()->get('/admin/notifications?email='.$user->email)->assertSee('Le cron du serveur tourne')
            ->assertSee('iPhone / iPad (Apple)')->assertSee('BadJwtToken');

        $this->actingAs($user)->get('/admin/notifications')->assertNotFound();
        $this->actingAs($user)->post('/admin/notifications/essai')->assertNotFound();
    }

    public function test_daily_reminder_reason_when_something_was_noted(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        UserProfile::updateOrCreate(['user_id' => $user->id], ['daily_reminder_at' => '00:00:00']);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/observations", ['body' => 'RAS', 'severity' => 'info']);
        $this->actingAs($user)->get('/parametres/notifications')->assertSee('data-daily-status', false)->assertSee('déjà noté');
    }

    public function test_scheduler_runs_from_web_requests_when_cron_is_missing(): void
    {
        config(['equine.scheduler_fallback_in_tests' => true]);
        Cache::forget(NotificationDiagnostics::CRON_KEY);
        Cache::forget(NotificationDiagnostics::FALLBACK_KEY);
        Cache::lock('scheduler-fallback')->forceRelease();
        $user = $this->user();
        $this->actingAs($user)->get('/dashboard');
        $this->assertNotNull(Cache::get(NotificationDiagnostics::FALLBACK_KEY), 'Le relais a lancé les tâches planifiées.');
        $this->assertNull(Cache::get(NotificationDiagnostics::CRON_KEY), 'Le relais ne se fait pas passer pour le cron.');
        $this->assertTrue(Cache::has('scheduler.fallback.reminders:send.'.today()->toDateString()) || now()->format('H:i') < '07:30');

        // Cron en place : plus de relais.
        Cache::forget(NotificationDiagnostics::FALLBACK_KEY);
        Cache::lock('scheduler-fallback')->forceRelease();
        NotificationDiagnostics::beat();
        $this->actingAs($user)->get('/dashboard');
        $this->assertNull(Cache::get(NotificationDiagnostics::FALLBACK_KEY));
    }

    public function test_device_receipt_is_recorded_and_shown(): void
    {
        $admin = $this->admin();
        app(WebPush::class)->publicKey(); // clés d'envoi déjà créées (leur création efface les abonnements)
        $sub = PushSubscription::forceCreate(['user_id' => $admin->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz', 'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/xyz'),
            'public_key' => 'x', 'auth_token' => 'y', 'content_encoding' => 'aes128gcm', 'device' => 'Android', 'last_success_at' => now()]);
        // Le service worker n'a ni session ni jeton CSRF.
        $this->postJson('/push/recu', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz'])->assertNoContent();
        $this->assertNotNull($sub->fresh()->last_received_at);
        $this->postJson('/push/recu', ['endpoint' => 'https://inconnu.example/abc'])->assertNoContent();
        $this->postJson('/push/recu', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz', 'error' => 'NotAllowedError'])->assertNoContent();
        $this->assertSame('NotAllowedError', $sub->fresh()->last_display_error);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/notifications')
            ->assertSee('Reçu et affiché par l')->assertSee('NotAllowedError')->assertSee('data-cron-runs', false);
    }

    public function test_device_reports_its_state(): void
    {
        $user = $this->user();
        app(WebPush::class)->publicKey();
        $sub = PushSubscription::forceCreate(['user_id' => $user->id, 'endpoint' => 'https://web.push.apple.com/zz', 'endpoint_hash' => hash('sha256', 'https://web.push.apple.com/zz'),
            'public_key' => 'x', 'auth_token' => 'y', 'content_encoding' => 'aes128gcm', 'device' => 'iPhone']);
        $this->actingAs($user)->postJson('/notifications/etat', ['endpoint' => 'https://web.push.apple.com/zz', 'permission' => 'granted', 'version' => 'v9', 'local_test' => 'ok'])
            ->assertOk()->assertJson(['known' => true, 'received_at' => null]);
        $this->assertSame(['granted', 'v9', 'ok'], [$sub->fresh()->device_permission, $sub->fresh()->sw_version, $sub->fresh()->local_test]);
        $this->postJson('/push/recu', ['endpoint' => 'https://web.push.apple.com/zz', 'version' => 'v9']);
        $this->actingAs($user)->postJson('/notifications/etat', ['endpoint' => 'https://web.push.apple.com/zz'])->assertJsonPath('known', true)->assertJsonMissing(['received_at' => null]);
        // L'appareil d'un autre utilisateur n'est pas modifiable.
        $this->actingAs($this->user())->postJson('/notifications/etat', ['endpoint' => 'https://web.push.apple.com/zz', 'permission' => 'denied'])->assertJson(['known' => false]);
        $this->assertSame('granted', $sub->fresh()->device_permission);
    }
}
