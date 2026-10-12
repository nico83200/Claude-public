<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Suggestion d'activer le rappel quotidien, de temps en temps, aux utilisateurs actifs qui ne l'ont pas coché. */
class DailyReminderNudgeTest extends TestCase
{
    private function activeUser(array $profile = []): User
    {
        $user = $this->user();
        $user->forceFill(['created_at' => now()->subDays(10), 'last_seen_at' => now()->subDay()])->save();
        UserProfile::updateOrCreate(['user_id' => $user->id], [])->forceFill($profile)->save();

        return $user;
    }

    private function nudges(User $user): int
    {
        return $user->notifications()->where('data->kind', 'daily_nudge')->count();
    }

    public function test_nudge_is_sent_at_most_three_times_two_weeks_apart(): void
    {
        $user = $this->activeUser();
        $enabled = $this->activeUser(['daily_reminder_at' => '19:00']);
        $newcomer = $this->user(); // inscrit aujourd'hui : on le laisse découvrir l'application
        $newcomer->forceFill(['last_seen_at' => now()])->save();
        $inactive = $this->activeUser();
        $inactive->forceFill(['last_seen_at' => now()->subDays(60)])->save();

        $noProfile = $this->user();
        $noProfile->forceFill(['created_at' => now()->subDays(10), 'last_seen_at' => now()])->save();
        UserProfile::where('user_id', $noProfile->id)->delete();

        Artisan::call('reminders:daily-nudge');
        $this->assertSame(1, $this->nudges($user));
        $this->assertSame(1, $this->nudges($noProfile), 'Un compte sans profil enregistré n\'a pas activé le rappel.');
        $this->assertSame(1, UserProfile::where('user_id', $noProfile->id)->value('daily_nudge_count'));
        $this->assertSame(0, $this->nudges($enabled) + $this->nudges($newcomer) + $this->nudges($inactive));
        $n = $user->notifications()->first()->data;
        $this->assertStringContainsString('/parametres/notifications?depuis=rappel#rappel-quotidien', $n['url']);

        Artisan::call('reminders:daily-nudge'); // le lendemain : pas de nouvel envoi
        $this->travel(13)->days();
        $user->forceFill(['last_seen_at' => now()])->save();
        Artisan::call('reminders:daily-nudge');
        $this->assertSame(1, $this->nudges($user));
        foreach ([1, 15, 15, 15] as $days) {
            $this->travel($days)->days();
            $user->forceFill(['last_seen_at' => now()])->save();
            Artisan::call('reminders:daily-nudge');
        }
        $this->assertSame(3, $this->nudges($user), 'Jamais plus de trois suggestions.');
    }

    public function test_one_tap_enable_and_never_again(): void
    {
        $user = $this->activeUser();
        $this->actingAs($user)->get('/parametres/notifications?depuis=rappel')->assertOk()->assertSee('data-daily-nudge', false)->assertSee('Activer à 19 h');
        $this->actingAs($user)->post('/parametres/notifications/rappel-quotidien')->assertRedirect('/parametres/notifications')->assertSessionHas('success');
        $this->assertSame('19:00', substr($user->profile->fresh()->daily_reminder_at, 0, 5));
        $this->actingAs($user = $user->fresh())->get('/parametres/notifications?depuis=rappel')->assertDontSee('data-daily-nudge', false);

        // Désactivé volontairement ensuite : plus de suggestion.
        $this->actingAs($user)->put('/parametres/notifications', ['debrief_reminders' => '1'])->assertRedirect();
        $this->assertNull($user->profile->fresh()->daily_reminder_at);
        $this->assertNotNull($user->profile->fresh()->daily_nudge_stopped_at);

        $other = $this->activeUser();
        $this->actingAs($other)->post('/parametres/notifications/rappel-quotidien/ne-plus-proposer')->assertSessionHas('success');
        Artisan::call('reminders:daily-nudge');
        $this->assertSame(0, $this->nudges($user) + $this->nudges($other));
    }
}
