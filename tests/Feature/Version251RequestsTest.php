<?php

namespace Tests\Feature;

use App\Models\HealthObservation;
use App\Models\HealthObservationPhoto;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\Professional;
use App\Models\User;
use App\Services\AttentionPoints;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Points d'attention : dossier suivi (historique, photos, vétérinaire à portée de main, relances), « Tout va bien aujourd'hui » retiré. */
class Version251RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
        app()->forgetInstance(AttentionPoints::class);
    }

    /** Uni (Paul) monté par Léa, qui peut écrire des observations ; samedi 24 octobre 2026, 9 h. */
    private function setUpHorse(): array
    {
        Carbon::setTestNow('2026-10-24 09:00');
        Storage::fake('local');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);
        $lea = $this->user(['name' => 'Léa DURAND']);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $lea->id, 'label' => 'Demi-pension', 'permissions' => [Perm::HORSE_VIEW, Perm::SESSIONS_CREATE, Perm::COMMENTS_CREATE, Perm::DAILYLOG_CREATE]]);
        $this->fresh();

        return [$owner, $horse, $lea];
    }

    private function vet(Horse $horse, array $attrs = []): Professional
    {
        return Professional::forceCreate($attrs + ['organization_id' => $horse->organization_id, 'kind' => 'veterinarian', 'first_name' => 'Anne', 'last_name' => 'VÉTO', 'phone' => '06 11 22 33 44']);
    }

    private function open(User $by, Horse $horse, array $data = [], array $photos = [])
    {
        $this->fresh();

        return $this->actingAs($by)->post(route('horses.observations.store', $horse), $data + [
            'title' => 'Saignement de nez', 'body' => 'Saignement de nez ce matin, revenu pendant la monte après nettoyage.', 'severity' => 'alert', 'photos' => $photos,
        ]);
    }

    private function titles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }

    public function test_nose_bleed_opens_a_calm_dossier_with_the_horse_vet_and_photos(): void
    {
        [$owner, $horse, $lea] = $this->setUpHorse();
        $this->vet($horse, ['last_name' => 'AUTRE', 'first_name' => 'Bruno', 'phone' => '06 99 99 99 99']);
        $own = $this->vet($horse);
        $horse->professionals()->attach($own->id, ['is_primary' => true]);

        // Avant même d'enregistrer : le message pour garder son calme et le vétérinaire du cheval.
        $this->actingAs($lea)->get(route('horses.observations.create', $horse))->assertOk()
            ->assertSee('Gardez votre calme')->assertSee('Vétérinaire de Uni')->assertSee('href="tel:0611223344"', false)->assertDontSee('AUTRE');

        $this->open($lea, $horse, [], [UploadedFile::fake()->image('narine.jpg', 800, 600)])->assertRedirect()->assertSessionHas('success');
        $point = HealthObservation::sole();
        $this->assertSame(['Saignement de nez', 'alert', 1], [$point->title, $point->severity, $point->photos()->count()]);
        $this->assertSame('2026-10-24 15:00', $point->follow_up_at->format('Y-m-d H:i'), 'Relance 6 h plus tard pour « À voir rapidement ».');
        $this->assertContains('Point d\'attention : Uni', $this->titles($owner));

        $this->fresh();
        $page = $this->actingAs($lea)->get(route('horses.observations.show', [$horse, $point]))->assertOk();
        $page->assertSee('Saignement de nez')->assertSee('Suivi en cours')->assertSee('Gardez votre calme')
            ->assertSee('appelez-le sans attendre')->assertSee('href="tel:0611223344"', false)->assertSee('data-attention-photos', false);
        $photo = HealthObservationPhoto::sole();
        $this->actingAs($lea)->get(route('horses.observations.photo', [$horse, $point, $photo]))->assertOk();
        // Un inconnu ne voit ni le dossier ni les photos.
        $stranger = $this->user();
        $this->fresh();
        $this->actingAs($stranger)->get(route('horses.observations.show', [$horse, $point]))->assertForbidden();
        $this->actingAs($stranger)->get(route('horses.observations.photo', [$horse, $point, $photo]))->assertForbidden();
    }

    public function test_vet_falls_back_to_the_first_vet_of_the_list_or_invites_to_add_one(): void
    {
        [$owner, $horse] = $this->setUpHorse();
        $this->actingAs($owner)->get(route('horses.observations.create', $horse))->assertSee('Aucun vétérinaire enregistré')->assertSee(route('professionals.create'), false);
        $this->vet($horse, ['last_name' => 'ZOLA', 'phone' => '06 00 00 00 02']);
        $this->vet($horse, ['last_name' => 'BERNARD', 'phone' => '06 00 00 00 01']);
        $this->fresh();
        $this->actingAs($owner)->get(route('horses.observations.create', $horse))
            ->assertSee('Vétérinaire de vos intervenants')->assertSee('BERNARD')->assertSee('href="tel:0600000001"', false)
            ->assertSee('Indiquer le vétérinaire de Uni');
    }

    public function test_news_history_closing_and_reopening(): void
    {
        [$owner, $horse, $lea] = $this->setUpHorse();
        $this->open($lea, $horse, ['severity' => 'watch']);
        $point = HealthObservation::sole();

        Carbon::setTestNow('2026-10-24 18:00');
        $this->fresh();
        $this->actingAs($lea)->post(route('horses.observations.updates.store', [$horse, $point]), [])->assertSessionHasErrors('body');
        $this->actingAs($lea)->post(route('horses.observations.updates.store', [$horse, $point]), [
            'trend' => 'worse', 'body' => 'Encore un peu de sang après le pansage.', 'photos' => [UploadedFile::fake()->image('soir.jpg')],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'appelez le vétérinaire'));
        $this->assertSame('2026-10-25 08:30', $point->fresh()->follow_up_at->format('Y-m-d H:i'), 'Aggravation : relance plus tôt, jamais la nuit.');
        $this->actingAs($lea)->post(route('horses.observations.updates.store', [$horse, $point]), ['trend' => 'better', 'body' => 'Vu par le vétérinaire, rien à signaler.']);
        $this->assertContains('Uni : Saignement de nez', $this->titles($owner));

        $this->fresh();
        $this->actingAs($owner)->get(route('horses.observations.show', [$horse, $point]))->assertOk()
            ->assertSeeInOrder(['Saignement de nez ce matin', 'Ça s\'aggrave', 'Encore un peu de sang', 'Ça va mieux', 'Vu par le vétérinaire']);

        // Léa (qui l'a ouvert) clôt ; Paul est prévenu ; plus de relance.
        $this->actingAs($lea)->post(route('horses.observations.resolve', [$horse, $point]), ['note' => 'Plus de saignement depuis 2 jours.'])->assertSessionHas('success');
        $point->refresh();
        $this->assertSame([$lea->id, null], [$point->resolved_by, $point->follow_up_at]);
        $this->assertContains('Uni : point d\'attention clos', $this->titles($owner));
        $this->fresh();
        $this->actingAs($lea)->get(route('horses.observations.show', [$horse, $point]))->assertSee('Rentré dans l\'ordre', false)->assertSee('Plus de saignement depuis 2 jours.')
            ->assertDontSee('data-attention-news', false)->assertSee('Rouvrir le suivi');
        $this->actingAs($lea)->post(route('horses.observations.updates.store', [$horse, $point]), ['body' => 'x'])->assertNotFound();
        $this->actingAs($owner)->post(route('horses.observations.reopen', [$horse, $point]))->assertSessionHas('success');
        $this->assertTrue($point->fresh()->isOpen());

        // Quelqu'un qui peut seulement voir le cheval ne clôt pas le dossier d'un autre.
        $viewer = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => [Perm::HORSE_VIEW, Perm::HEALTH_VIEW]]);
        $this->fresh();
        $this->actingAs($viewer)->post(route('horses.observations.resolve', [$horse, $point]))->assertForbidden();
    }

    public function test_follow_up_notifications_with_quick_answers(): void
    {
        [$owner, $horse, $lea] = $this->setUpHorse();
        $this->open($lea, $horse, ['severity' => 'watch']);
        $point = HealthObservation::sole();

        $this->artisan('attention:follow-up')->assertSuccessful();
        $this->assertNotContains('Des nouvelles de Uni ?', $this->titles($lea), 'Pas avant l\'heure.');
        Carbon::setTestNow('2026-10-25 09:30');
        $this->fresh();
        $this->artisan('attention:follow-up');
        $notes = $lea->notifications()->get()->filter(fn ($n) => $n->data['title'] === 'Des nouvelles de Uni ?');
        $this->assertSame(1, $notes->count());
        $this->assertSame(route('horses.observations.show', [$horse, $point]), $notes->first()->data['url']);
        $this->assertSame(1, $point->fresh()->follow_up_count);

        // Les boutons de la notification : « Ça va mieux », puis « C'est rentré dans l'ordre ».
        $points = app(AttentionPoints::class);
        [$better, $close] = $points->notificationActions($point, $lea);
        $this->actingAs($owner)->postJson($better['url'])->assertForbidden();
        $this->actingAs($lea)->postJson(route('observations.quick', ['observation' => $point->id, 'action' => 'mieux', 'user' => $lea->id]))->assertForbidden();
        $this->fresh();
        $this->actingAs($lea)->postJson($better['url'])->assertOk()->assertJson(['message' => 'Uni : « ça va mieux » noté. On vous redemandera des nouvelles.']);
        $this->assertSame(['better', 0], [$point->updates()->sole()->trend, $point->fresh()->follow_up_count]);
        $this->fresh();
        $this->actingAs($lea)->postJson($close['url'])->assertOk();
        $this->assertFalse($point->fresh()->isOpen());
        $this->fresh();
        $this->actingAs($lea)->postJson($close['url'])->assertOk()->assertJson(['message' => 'Uni : ce point d\'attention est déjà clos.']);
    }

    public function test_follow_ups_stop_after_four_unanswered_and_never_at_night(): void
    {
        $this->assertSame('2026-10-25 08:30', AttentionPoints::nextFollowUp('alert', Carbon::parse('2026-10-24 19:00'))->format('Y-m-d H:i'));
        $this->assertSame('2026-10-25 08:30', AttentionPoints::nextFollowUp('alert', Carbon::parse('2026-10-25 00:30'))->format('Y-m-d H:i'));
        $this->assertSame('2026-10-24 15:00', AttentionPoints::nextFollowUp('alert', Carbon::parse('2026-10-24 09:00'))->format('Y-m-d H:i'));

        [, $horse, $lea] = $this->setUpHorse();
        $this->open($lea, $horse, ['severity' => 'watch']);
        $point = HealthObservation::sole();
        foreach (range(1, 6) as $day) {
            Carbon::setTestNow(Carbon::parse('2026-10-24 10:00')->addDays($day));
            $this->fresh();
            $this->artisan('attention:follow-up');
        }
        $this->assertSame(4, $lea->notifications()->get()->filter(fn ($n) => $n->data['title'] === 'Des nouvelles de Uni ?')->count());
        $this->assertNull($point->fresh()->follow_up_at);
        $this->assertTrue($point->fresh()->isOpen(), 'Le dossier reste ouvert.');
    }

    public function test_dashboard_without_all_good_button_and_clickable_alert(): void
    {
        [$owner, $horse] = $this->setUpHorse();
        $page = $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard-solo');
        $page->assertDontSee('Tout va bien aujourd\'hui')->assertSee('data-solo-attention-new', false)->assertSee(route('horses.observations.create', $horse), false);
        $this->actingAs($owner)->get(route('horses.logs.index', $horse))->assertOk()->assertDontSee('Tout va bien aujourd\'hui')->assertSee('data-log-attention', false);

        $this->open($owner, $horse);
        $point = HealthObservation::sole();
        $this->fresh();
        $this->actingAs($owner)->get(route('dashboard'))->assertSee('data-solo-attention', false)->assertSee('data-solo-state="red"', false)
            ->assertSee('Point d&#039;attention : Saignement de nez', false)->assertSee(route('horses.observations.show', [$horse, $point]), false);
        $this->actingAs($owner)->get(route('health.index'))->assertSee(route('horses.observations.show', [$horse, $point]), false);
        $this->actingAs($owner)->get(route('horses.show', $horse))->assertSee('Points d\'attention en cours')->assertSee(route('horses.observations.create', $horse), false);

        // Plusieurs chevaux : la ligne du cheval mène aussi au dossier.
        $this->horseFor($owner, null, ['official_name' => 'DEUXIEME', 'usual_name' => 'Deux']);
        $this->fresh();
        $this->actingAs($owner)->post(route('focus.set'), ['horse' => 'all']);
        $this->actingAs($owner)->get(route('dashboard'))->assertViewIs('dashboard')->assertSee('data-horse-alert', false)
            ->assertSee(route('horses.observations.show', [$horse, $point]), false);
    }

    public function test_assistant_observation_is_followed_up_too(): void
    {
        [, $horse, $lea] = $this->setUpHorse();
        $point = HealthObservation::create(['horse_id' => $horse->id, 'author_id' => $lea->id, 'observed_at' => now(), 'severity' => 'info', 'body' => 'Petite égratignure au boulet',
            'follow_up_at' => AttentionPoints::nextFollowUp('info')]);
        $this->assertSame('Petite égratignure au boulet', $point->label());
        $this->assertSame('2026-10-27 09:00', $point->follow_up_at->format('Y-m-d H:i'), 'Simple remarque : relance à 3 jours.');
    }
}
