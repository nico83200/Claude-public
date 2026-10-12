<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\UserProfile;
use App\Services\NotificationDiagnostics;
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
            ->assertSee('Le cron du serveur ne tourne pas')->assertSee('php artisan schedule:run')->assertSee(base_path('cron.php'))
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
}
