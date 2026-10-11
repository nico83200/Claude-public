<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Notifications\AppNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InactivityReminderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_opening_the_app_records_last_visit(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertTrue($user->fresh()->last_seen_at->isToday());
    }

    public function test_reminder_after_configured_hours_once_per_absence_and_only_in_daytime(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-10-12 10:00');
        ApplicationSetting::put('inactivity_hours', 24);
        $away = $this->user(['last_seen_at' => now()->subHours(30)]);
        $recent = $this->user(['last_seen_at' => now()->subHours(3)]);
        $suspended = $this->user(['last_seen_at' => now()->subHours(30), 'suspended_at' => now()]);

        $this->artisan('reminders:activity')->assertSuccessful();
        $this->artisan('reminders:activity')->assertSuccessful();
        Notification::assertSentToTimes($away, AppNotification::class, 1);
        Notification::assertSentTo($away, AppNotification::class, fn ($n) => $n->kind === 'inactivity' && ! $n->mail && str_contains($n->body, 'Vos chevaux vous attendent'));
        Notification::assertNotSentTo($recent, AppNotification::class);
        Notification::assertNotSentTo($suspended, AppNotification::class);

        // Nouvelle absence après une visite : nouveau rappel, avec le texte de l'administrateur ; jamais la nuit.
        ApplicationSetting::put('inactivity_message', 'Uni attend sa séance !');
        $away->forceFill(['last_seen_at' => now()->subHours(1)])->save();
        Carbon::setTestNow('2026-10-13 22:30');
        $this->artisan('reminders:activity');
        Notification::assertSentToTimes($away, AppNotification::class, 1);
        Carbon::setTestNow('2026-10-14 09:15');
        $this->artisan('reminders:activity');
        Notification::assertSentToTimes($away, AppNotification::class, 2);
        Notification::assertSentTo($away, AppNotification::class, fn ($n) => $n->body === 'Uni attend sa séance !');
    }

    public function test_disabled_when_zero_and_configurable_by_super_admin(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-10-12 10:00');
        $this->user(['last_seen_at' => now()->subDays(3)]);
        $this->artisan('reminders:activity');
        Notification::assertNothingSent();

        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])->get('/admin/parametres')->assertOk()->assertSee('Rappel après une absence');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])->put('/admin/parametres', ['app_name' => 'Jack&Cie', 'inactivity_hours' => 48, 'inactivity_message' => 'Coucou'])->assertSessionHas('success');
        $this->assertSame(48, ApplicationSetting::get('inactivity_hours'));
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])->put('/admin/parametres', ['app_name' => 'Jack&Cie', 'inactivity_hours' => 9999])->assertSessionHasErrors('inactivity_hours');
    }
}
