<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CareRhythm;
use App\Services\Entitlements;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Rappels intelligents : rythme réel des soins et proposition de rendez-vous. */
class CareRhythmTest extends TestCase
{
    /** Maréchal toutes les 7 semaines environ, dernier passage il y a $lastWeeksAgo semaines. */
    private function farrierHistory(Horse $horse, User $user, int $lastWeeksAgo): void
    {
        $cat = CareCategory::where('key', 'farrier')->first();
        foreach ([$lastWeeksAgo + 21, $lastWeeksAgo + 14, $lastWeeksAgo + 7, $lastWeeksAgo] as $i => $weeks) {
            CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $cat->id, 'author_id' => $user->id,
                'performed_at' => today()->subWeeks($weeks)->addDays($i === 1 ? 1 : 0)->setTime(10, 0), 'reason' => 'Ferrure']);
        }
    }

    public function test_rhythm_is_computed_from_history_and_no_longer_shown_on_the_health_page(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->farrierHistory($horse, $user, 6);

        $r = app(CareRhythm::class)->forHorse($horse)->first();
        $this->assertSame('Maréchal-ferrant', $r['category']->name);
        $this->assertSame(49, $r['interval_days']);
        $this->assertSame('7 semaines', $r['label']);
        $this->assertSame(7, $r['due_in']);

        // Plus affiché sur la page santé du cheval (demande 2.21) : le rythme sert aux rappels.
        $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertOk()
            ->assertDontSee('Rythme habituel')->assertDontSee('Toutes les 7 semaines en moyenne');

        // La règle par défaut du maréchal diffère du rythme observé : on peut l'aligner.
        $this->actingAs($user)->post("/chevaux/{$horse->id}/rythme-habituel", ['category_id' => $r['category']->id])->assertSessionHas('success');
        $this->assertDatabaseHas('care_reminder_rules', ['horse_id' => $horse->id, 'care_category_id' => $r['category']->id, 'interval_value' => 7, 'interval_unit' => 'weeks']);
        $this->assertFalse(CareRhythm::differsFromRule(app(CareRhythm::class)->forHorse($horse)->first()));

        // Lien « prendre rendez-vous » : formulaire pré-rempli.
        $this->actingAs($user)->get('/calendrier?nouveau=1&horse='.$horse->id.'&type=farrier&title=Mar%C3%A9chal-ferrant&date='.today()->addDays(7)->toDateString())
            ->assertOk()->assertSee('value="Maréchal-ferrant"', false)->assertSee("document.getElementById('new-event').showModal()", false);
    }

    public function test_notification_once_when_due_and_none_when_appointment_planned(): void
    {
        Notification::fake();
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->farrierHistory($horse, $user, 6); // échéance dans 7 jours : trop tôt
        $this->artisan('reminders:rhythm')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(3)->days(); // échéance dans 4 jours
        $this->artisan('reminders:rhythm')->assertSuccessful();
        $this->artisan('reminders:rhythm')->assertSuccessful();
        Notification::assertSentToTimes($user, AppNotification::class, 1);
        Notification::assertSentTo($user, AppNotification::class, fn ($n) => $n->title === 'Maréchal-ferrant : on prend rendez-vous ?'
            && str_contains($n->body, 'toutes les 7 semaines') && str_contains($n->url, 'nouveau=1'));

        // Rendez-vous pris pour un autre cheval de même rythme : pas de rappel.
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        app(Entitlements::class)->flush();
        $other = $this->horseFor($user);
        $this->farrierHistory($other, $user, 7);
        CalendarEvent::forceCreate(['horse_id' => $other->id, 'organization_id' => $other->organization_id, 'type' => 'farrier', 'title' => 'Maréchal', 'starts_at' => now()->addDays(2), 'status' => 'planned', 'created_by' => $user->id]);
        $this->artisan('reminders:rhythm')->assertSuccessful();
        Notification::assertSentToTimes($user, AppNotification::class, 1);
    }

    public function test_too_little_history_gives_no_rhythm(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $cat = CareCategory::where('key', 'farrier')->first();
        CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $cat->id, 'author_id' => $user->id, 'performed_at' => now()->subWeeks(7)]);
        CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $cat->id, 'author_id' => $user->id, 'performed_at' => now()]);
        $this->assertTrue(app(CareRhythm::class)->forHorse($horse)->isEmpty());
        $this->actingAs($user)->get("/chevaux/{$horse->id}/sante")->assertOk()->assertDontSee('Rythme habituel');
    }
}
