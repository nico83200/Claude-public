<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\CalendarEvent;
use App\Models\DailyLog;
use App\Models\Exercise;
use App\Models\PushSubscription;
use App\Models\RidingSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AppNotification;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Assistant\ClaudeGateway;
use App\Services\RidingSessionService;
use App\Services\WebPush;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\FakeClaude;
use Tests\TestCase;

class LessonRemindersTest extends TestCase
{
    private function lessonFor($user, $horse, array $attrs = []): RidingSession
    {
        $attrs += ['scheduled_at' => now()->subHours(2), 'session_type' => 'group_lesson', 'instructor' => 'Jimmy', 'planned_minutes' => 60, 'status' => 'planned'];
        // Cours prévu à l'avance (une séance saisie après coup est directement enregistrée comme terminée).
        $this->travelTo(now()->subDays(2));
        $session = app(RidingSessionService::class)->create($horse, $user, $attrs);
        $this->travelBack();

        return $session;
    }

    public function test_dictated_lesson_becomes_a_session_in_the_calendar(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($user, null, ['official_name' => 'JACK JACK DE COURTIS', 'usual_name' => 'Jack Jack']);
        $fake = new FakeClaude([
            ['tools' => [['create_session', ['horse_id' => $horse->id, 'scheduled_at' => now()->addDay()->format('Y-m-d').' 16:00', 'session_type' => 'group_lesson', 'instructor' => 'Jimmy', 'planned_minutes' => 60, 'exercises' => []]]]],
            ['text' => 'C\'est noté : cours collectif avec Jimmy pour Jack Jack demain à 16 h.'],
        ]);
        $this->app->instance(ClaudeGateway::class, $fake);

        $res = $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Une monte en cours collectif est prévue demain à seize heures avec Jimmy sur Jack Jack.', 'mode' => 'voice'])->assertOk();

        $session = RidingSession::firstOrFail();
        $this->assertSame(['group_lesson', 'Jimmy', 60, 'planned'], [$session->session_type, $session->instructor, $session->planned_minutes, $session->status]);
        $this->assertSame('Cours collectif avec Jimmy', $session->title());
        $event = CalendarEvent::where('source_id', $session->id)->where('type', 'session')->firstOrFail();
        $this->assertSame('Cours collectif avec Jimmy – Jack Jack', $event->title);
        $this->assertSame(now()->addDay()->format('Y-m-d').' 16:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertStringStartsWith('Cours collectif avec Jimmy – Jack Jack', $res->json('actions.0.summary'));
        // Les règles transmises à Claude font d'un cours une séance, sans exercices inventés.
        $system = collect($fake->calls[0]['system'])->pluck('text')->implode("\n");
        $this->assertStringContainsString('toujours create_session', $system);
        $this->assertStringContainsString('instructor', json_encode($fake->calls[0]['tools']));
    }

    public function test_debrief_reminder_is_sent_once_after_the_lesson(): void
    {
        Notification::fake();
        $user = $this->user();
        $horse = $this->horseFor($user, null, ['usual_name' => 'Jack Jack']);
        $session = $this->lessonFor($user, $horse);
        $later = $this->lessonFor($user, $horse, ['scheduled_at' => now()->subMinutes(30)]); // pas encore fini (60 min + 10)
        $done = $this->lessonFor($user, $horse, ['scheduled_at' => now()->subHours(3)]);
        $done->update(['status' => 'completed']);

        $this->artisan('reminders:activity')->assertSuccessful();
        $this->artisan('reminders:activity')->assertSuccessful();

        Notification::assertSentToTimes($user, AppNotification::class, 1);
        Notification::assertSentTo($user, AppNotification::class, function (AppNotification $n) use ($session) {
            return $n->kind === 'session_debrief' && $n->url === route('sessions.debrief', $session) && ! $n->mail
                && $n->title === 'Comment s\'est passé votre cours ?' && str_contains($n->body, 'Cours collectif avec Jimmy – Jack Jack');
        });
    }

    public function test_debrief_reminder_respects_preference_and_access(): void
    {
        Notification::fake();
        $user = $this->user();
        UserProfile::updateOrCreate(['user_id' => $user->id], ['debrief_reminders' => false]);
        $this->lessonFor($user, $this->horseFor($user));
        $this->artisan('reminders:activity')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_daily_reminder_only_when_nothing_was_written(): void
    {
        Notification::fake();
        $quiet = $this->user();
        $this->horseFor($quiet);
        UserProfile::updateOrCreate(['user_id' => $quiet->id], ['daily_reminder_at' => '00:00:00']);
        $busy = $this->user();
        $busyHorse = $this->horseFor($busy);
        UserProfile::updateOrCreate(['user_id' => $busy->id], ['daily_reminder_at' => '00:00:00']);
        DailyLog::create(['uuid' => (string) Str::uuid(), 'horse_id' => $busyHorse->id, 'author_id' => $busy->id, 'logged_at' => now()]);
        $off = $this->user();
        $this->horseFor($off);

        $this->artisan('reminders:activity')->assertSuccessful();
        $this->artisan('reminders:activity')->assertSuccessful();

        Notification::assertSentToTimes($quiet, AppNotification::class, 1);
        Notification::assertSentTo($quiet, AppNotification::class, fn ($n) => $n->kind === 'daily_reminder' && ! $n->mail);
        Notification::assertNotSentTo($busy, AppNotification::class);
        Notification::assertNotSentTo($off, AppNotification::class);
    }

    public function test_debrief_records_worked_exercises_and_closes_the_lesson(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $session = $this->lessonFor($user, $horse);
        $library = Exercise::where('scope', 'default')->firstOrFail();

        $this->actingAs($user)->get(route('sessions.debrief', $session))->assertOk()->assertSee('Bilan du cours')->assertSee('Ce que vous avez travaillé');
        $this->actingAs($user)->put(route('sessions.debrief.save', $session), [
            'added' => [['name' => mb_strtoupper($library->name), 'minutes' => 15], ['name' => 'Enchaînement de 6 obstacles', 'minutes' => 25], ['name' => '', 'minutes' => null]],
            'rider_feeling' => 4, 'progress' => 'Meilleur équilibre',
        ])->assertRedirect(route('sessions.show', $session));

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $items = $session->items()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame([$library->id, 'done', 15], [$items[0]->exercise_id, $items[0]->status, $items[0]->actual_minutes]);
        $this->assertSame([null, 'Enchaînement de 6 obstacles', 'done'], [$items[1]->exercise_id, $items[1]->name, $items[1]->status]);
        $this->assertSame('done', CalendarEvent::where('source_id', $session->id)->where('type', 'session')->value('status'));
    }

    public function test_push_subscription_accepts_only_browser_push_services(): void
    {
        $user = $this->user();
        $keys = ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'];
        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'https://169.254.169.254/latest', 'keys' => $keys])->assertStatus(422);
        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'http://fcm.googleapis.com/fcm/send/x', 'keys' => $keys])->assertStatus(422);
        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'https://evil.example/fcm.googleapis.com', 'keys' => $keys])->assertStatus(422);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc:def';
        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => $endpoint, 'keys' => $keys, 'contentEncoding' => 'aes128gcm'])->assertOk()->assertJson(['devices' => 1]);
        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'https://web.push.apple.com/QGx', 'keys' => $keys])->assertOk()->assertJson(['devices' => 2]);
        // Même appareil, autre compte : l'abonnement change de propriétaire.
        $other = $this->user();
        $this->actingAs($other)->postJson(route('push.subscribe'), ['endpoint' => $endpoint, 'keys' => $keys])->assertOk()->assertJson(['devices' => 1]);
        $this->assertSame(1, PushSubscription::where('user_id', $user->id)->count());

        $this->actingAs($other)->deleteJson(route('push.unsubscribe'), ['endpoint' => $endpoint])->assertOk();
        $this->assertSame(0, PushSubscription::where('user_id', $other->id)->count());
    }

    public function test_notifications_go_to_subscribed_devices_and_settings_are_saved(): void
    {
        $user = $this->user();
        $this->horseFor($user);
        $sent = [];
        $this->app->instance(WebPush::class, new class($sent) extends WebPush
        {
            public function __construct(private array &$sent) {}

            public function publicKey(): string
            {
                return 'BPUBLIC';
            }

            public function send(User $user, array $payload): int
            {
                $this->sent[] = $payload;

                return 1;
            }
        });
        $note = new AppNotification('session_debrief', 'Titre', 'Corps', 'https://jackcie.test/x', mail: false);
        $this->assertSame(['database'], $note->via($user));
        PushSubscription::create(['user_id' => $user->id, 'endpoint' => 'https://fcm.googleapis.com/x', 'endpoint_hash' => hash('sha256', 'x'), 'public_key' => 'k', 'auth_token' => 'a']);
        $this->assertSame(['database', WebPushChannel::class], $note->via($user));
        $user->notify($note);
        $this->assertSame([['title' => 'Titre', 'body' => 'Corps', 'url' => 'https://jackcie.test/x', 'tag' => 'session_debrief']], $sent);

        $this->actingAs($user)->get(route('settings.notifications'))->assertOk()->assertSee('BPUBLIC')->assertSee('Après une séance ou un cours');
        $this->actingAs($user)->postJson(route('push.test'))->assertOk()->assertJson(['reached' => 1]);
        $this->actingAs($user)->put(route('settings.notifications.update'), ['debrief_reminders' => '0', 'daily_reminder' => '1', 'daily_reminder_at' => '19:30'])->assertSessionHas('success');
        $profile = $user->profile()->first();
        $this->assertSame([false, '19:30:00', false], [(bool) $profile->debrief_reminders, $profile->daily_reminder_at, (bool) $profile->email_notifications]);
        $this->actingAs($user)->put(route('settings.notifications.update'), ['debrief_reminders' => '1', 'email_notifications' => '1'])->assertSessionHas('success');
        $this->assertNull($user->profile()->first()->daily_reminder_at);
    }

    public function test_vapid_keys_are_generated_once_and_private_key_is_encrypted(): void
    {
        $push = app(WebPush::class);
        $key = $push->publicKey();
        $this->assertSame(87, strlen($key));
        $this->assertSame($key, app(WebPush::class)->publicKey());
        $stored = ApplicationSetting::where('key', 'webpush.vapid')->value('value');
        $this->assertNotEmpty(Crypt::decryptString($stored['private']));
        $this->assertStringNotContainsString(Crypt::decryptString($stored['private']), json_encode($stored));
    }

    public function test_notification_gate_is_on_every_app_page_except_personal_data(): void
    {
        $user = $this->user();
        // Sans cheval, l'écran n'est pas encore proposé (le compte n'a rien à rappeler).
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
        preg_match('#<script type="application/json" id="push-config">(.*?)</script>#s', $html, $m);
        $this->assertFalse(json_decode($m[1], true)['enabled']);
        $this->horseFor($user);
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Activez les notifications')->assertSee('id="push-config"', false)->getContent();
        preg_match('#<script type="application/json" id="push-config">(.*?)</script>#s', $html, $m);
        $config = json_decode($m[1], true);
        $this->assertTrue($config['enabled']);
        $this->assertSame(app(WebPush::class)->publicKey(), $config['vapidKey']);
        $this->assertSame(route('push.subscribe'), $config['subscribeUrl']);

        $html = $this->actingAs($user)->get('/parametres/donnees')->assertOk()->getContent();
        preg_match('#id="push-config">(.*?)</script>#s', $html, $m);
        $this->assertFalse(json_decode($m[1], true)['enabled']);
    }
}
