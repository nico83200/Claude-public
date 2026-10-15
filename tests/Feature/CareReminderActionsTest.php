<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CareReminders;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Rappel de soin : « C'est fait » et « Reporter d'une semaine » directement depuis la notification du téléphone. */
class CareReminderActionsTest extends TestCase
{
    private function dueCare(User $owner, Horse $horse, int $inDays = 0): CareRecord
    {
        $farrier = CareCategory::where('key', 'farrier')->value('id');
        $this->actingAs($owner)->post("/chevaux/{$horse->id}/soins", ['care_category_id' => $farrier, 'performed_at' => now()->subWeeks(7)->format('Y-m-d').' 10:00',
            'reason' => 'Parage', 'next_mode' => 'date', 'next_check_on' => now()->addDays($inDays)->toDateString()])->assertRedirect();

        return CareRecord::where('horse_id', $horse->id)->latest('id')->firstOrFail();
    }

    private function actionUrl(CareRecord $care, User $user, string $action): string
    {
        return collect(app(CareReminders::class)->notificationActions($care, $user))->firstWhere('action', $action)['url'];
    }

    public function test_the_reminder_carries_the_buttons_only_for_people_who_can_edit_health(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $care = $this->dueCare($owner, $horse);
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'health.view']]);

        Notification::fake();
        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($owner, AppNotification::class, function (AppNotification $n) use ($owner) {
            $push = $n->toWebPush($owner);

            return $n->kind === 'care_due' && array_column($push['actions'], 'title') === ['C\'est fait', 'Reporter d\'une semaine']
                && str_contains($push['actions'][0]['url'], '/rappels/soins/') && str_contains($push['actions'][0]['url'], 'signature=');
        });
        Notification::assertSentTo($viewer, AppNotification::class, fn (AppNotification $n) => $n->kind === 'care_due' && ! isset($n->toWebPush($viewer)['actions']));
        $this->assertNotNull($care->fresh()->next_check_on);
    }

    public function test_done_records_the_care_today_and_closes_the_reminder_once(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $care = $this->dueCare($owner, $horse);
        $checkEvent = fn () => CalendarEvent::where('source_type', $care->getMorphClass())->where('source_id', $care->id)->first();
        $this->assertSame('planned', $checkEvent()->status);
        $url = $this->actionUrl($care, $owner, 'done');

        $res = $this->actingAs($owner)->postJson($url)->assertOk();
        $new = CareRecord::where('horse_id', $horse->id)->latest('id')->firstOrFail();
        $this->assertNotSame($care->id, $new->id);
        $this->assertTrue($new->performed_at->isToday());
        $this->assertSame([$care->care_category_id, 'Parage', $owner->id], [$new->care_category_id, $new->reason, $new->author_id]);
        // Échéance suivante : fréquence du maréchal pour ce cheval (règle par défaut de l'application).
        $rule = app(CareReminders::class)->ruleFor($horse, $care->care_category_id);
        $this->assertSame(CareReminders::addInterval(now(), $rule['interval_value'], $rule['interval_unit'])->toDateString(), $new->next_check_on->toDateString());
        $this->assertNotNull($care->fresh()->next_check_done_at);
        $this->assertSame('done', $checkEvent()->status);
        $this->assertStringContainsString('noté comme fait aujourd\'hui', $res->json('message'));
        $this->assertSame(route('horses.care.show', [$horse, $new]), $res->json('url'));

        // Second appui (autre appareil) : rien de plus.
        $this->actingAs($owner)->postJson($url)->assertOk()->assertJsonPath('message', 'Maréchal-ferrant de '.$horse->shortName().' : déjà noté comme fait.');
        $this->assertSame(2, CareRecord::where('horse_id', $horse->id)->count());
    }

    public function test_later_moves_the_due_date_by_a_week_without_stacking(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $care = $this->dueCare($owner, $horse, 7);
        $url = $this->actionUrl($care, $owner, 'later');
        $expected = now()->addDays(14)->toDateString();

        $this->actingAs($owner)->postJson($url)->assertOk()->assertJsonFragment(['message' => 'Maréchal-ferrant de '.$horse->shortName().' : échéance reportée au '.now()->addDays(14)->format('d/m/Y').'.']);
        $this->assertSame($expected, $care->fresh()->next_check_on->toDateString());
        // Le contrôle prévu dans l'agenda suit.
        $this->assertSame($expected, CalendarEvent::where('source_type', $care->getMorphClass())->where('source_id', $care->id)->first()->starts_at->toDateString());

        // Même bouton touché deux fois : reporté une seule fois.
        $this->actingAs($owner)->postJson($url)->assertOk();
        $this->assertSame($expected, $care->fresh()->next_check_on->toDateString());

        // Depuis le navigateur (sans JSON) : retour sur la santé du cheval avec le message.
        $this->actingAs($owner)->post($this->actionUrl($care->fresh(), $owner, 'later'))->assertRedirect(route('horses.care.index', $horse))->assertSessionHas('success');
    }

    public function test_buttons_cannot_be_used_by_someone_else_or_without_rights(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $care = $this->dueCare($owner, $horse);
        $url = $this->actionUrl($care, $owner, 'done');

        // Pas connecté : refus (le téléphone ouvre alors l'application).
        auth()->logout();
        $this->postJson($url)->assertUnauthorized();
        // Adresse modifiée : signature invalide.
        $this->actingAs($owner)->postJson(str_replace('/fait?', '/reporter?', $url))->assertForbidden();
        // Adresse d'une autre personne, même avec les droits sur le cheval.
        $coOwner = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $coOwner->id, 'permissions' => ['horse.view', 'health.view', 'health.edit']]);
        $this->actingAs($coOwner)->postJson($url)->assertForbidden();
        // Droit retiré depuis l'envoi : refus.
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view', 'health.view']]);
        $this->actingAs($viewer)->postJson($this->actionUrl($care, $viewer, 'done'))->assertForbidden();

        $this->assertNull($care->fresh()->next_check_done_at);
        $this->assertSame(1, CareRecord::where('horse_id', $horse->id)->count());
    }
}
