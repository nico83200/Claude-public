<?php

namespace Tests\Feature;

use App\Models\AssistantRequest;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Exercise;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HealthObservation;
use App\Models\HealthShare;
use App\Models\HorseAccessGrant;
use App\Models\HorseDocument;
use App\Models\HorseMeasurement;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Assistant\ClaudeGateway;
use App\Services\HorseAccess;
use App\Services\SystemMonitor;
use App\Services\WeeklyDigest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeClaude;
use Tests\TestCase;

/** Fonctions IA (ordonnance, questions, résumé vétérinaire, plan, récapitulatif, voix) et améliorations techniques. */
class AiFeaturesTest extends TestCase
{
    private function setUpUser(string $plan = 'particulier'): array
    {
        config(['services.anthropic.key' => 'sk-ant-test']);
        $user = $this->user(['name' => 'Léa Martin']);
        $this->grantLicense($user->personalOrganization(), $plan);
        $horse = $this->horseFor($user, null, ['official_name' => 'UNI DU BOIS', 'usual_name' => 'Uni', 'main_discipline' => 'dressage']);

        return [$user, $horse];
    }

    private function fake(): FakeClaude
    {
        $fake = new FakeClaude([]);
        $this->app->instance(ClaudeGateway::class, $fake);

        return $fake;
    }

    public function test_prescription_photo_creates_care_treatments_and_next_check_after_review(): void
    {
        Storage::fake('local');
        [$user, $horse] = $this->setUpUser();
        $fake = $this->fake();
        $fake->prescription = ['is_prescription' => true, 'practitioner' => 'Dr Anne Leroy, Clinique du Var', 'visit_date' => now()->subDay()->toDateString(),
            'care_category' => 'veterinary', 'horse_name' => 'Uni', 'reason' => 'Boiterie antérieur gauche', 'diagnosis' => 'Abcès de pied', 'instructions' => 'Bain de pied 2 fois par jour',
            'next_check_on' => '', 'next_check_days' => 10, 'confidence' => 'high', 'treatments' => [
                ['product' => 'Equipalazone', 'dosage' => '1 sachet matin et soir', 'frequency' => 'matin et soir', 'times_per_day' => 2, 'duration_days' => 5, 'route' => 'orale'],
                ['product' => 'Betadine', 'dosage' => 'application locale', 'frequency' => '1 fois par jour', 'times_per_day' => 1, 'duration_days' => 0, 'route' => 'cutanée'],
            ]];
        $vet = \App\Models\Professional::forceCreate(['organization_id' => $horse->organization_id, 'last_name' => 'Leroy', 'first_name' => 'Anne', 'kind' => 'veterinarian']);

        $this->actingAs($user)->get(route('horses.care.index', $horse))->assertSee('data-prescription-card', false);
        $res = $this->actingAs($user)->post(route('horses.prescription.read', $horse), ['prescription' => UploadedFile::fake()->image('ordonnance.jpg', 1200, 1600)]);
        $res->assertRedirect();
        $review = $res->headers->get('Location');
        $this->assertSame(0, CareRecord::where('horse_id', $horse->id)->count());
        $this->assertDatabaseHas('assistant_requests', ['user_id' => $user->id, 'input_mode' => 'document']);
        $page = $this->actingAs($user)->get($review)->assertOk()->assertSee('data-prescription-review', false)->assertSee('Abcès de pied')->assertSee('Equipalazone')->assertSee('1 sachet matin et soir');
        $page->assertSee('value="'.$vet->id.'" selected', false);
        $token = basename($review);

        $visit = now()->subDay()->toDateString();
        $this->actingAs($user)->post(route('horses.prescription.save', [$horse, $token]), [
            'care_category_id' => CareCategory::where('key', 'veterinary')->value('id'), 'professional_id' => $vet->id, 'performed_at' => $visit,
            'reason' => 'Boiterie antérieur gauche', 'diagnosis' => 'Abcès de pied', 'instructions' => 'Bain de pied', 'next_check_on' => now()->addDays(9)->toDateString(), 'keep_document' => 1,
            'treatments' => [
                ['create' => 1, 'product' => 'Equipalazone', 'dosage' => '1 sachet matin et soir', 'frequency' => 'matin et soir', 'times_per_day' => 2, 'starts_on' => $visit, 'ends_on' => now()->addDays(3)->toDateString(), 'instructions' => 'orale'],
                ['create' => 0, 'product' => 'Betadine'],
            ],
        ])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '1 traitement'));

        $care = CareRecord::where('horse_id', $horse->id)->sole();
        $this->assertSame(['Abcès de pied', $vet->id, now()->addDays(9)->toDateString()], [$care->diagnosis, $care->professional_id, $care->next_check_on->toDateString()]);
        $t = Treatment::where('horse_id', $horse->id)->sole();
        $this->assertSame(['Equipalazone', '1 sachet matin et soir', 2, $care->id, 'active'], [$t->product, $t->dosage, $t->times_per_day, $t->care_record_id, $t->status]);
        $this->assertSame('prescription', HorseDocument::where('horse_id', $horse->id)->sole()->category);
        // Prochain contrôle et fin de traitement synchronisés dans l'agenda.
        $this->assertTrue(\App\Models\CalendarEvent::where('treatment_id', $t->id)->exists());
        $this->actingAs($user)->get($review)->assertNotFound();

        // Sans l'assistant dans l'offre : pas de lecture.
        $free = $this->user();
        $h2 = $this->horseFor($free);
        $before = count($fake->calls);
        $this->actingAs($free)->post(route('horses.prescription.read', $h2), ['prescription' => UploadedFile::fake()->image('o.jpg')])->assertRedirect();
        $this->assertCount($before, $fake->calls);
        $this->assertSame(1, CareRecord::count());
    }

    public function test_assistant_answers_totals_from_the_record(): void
    {
        [$user, $horse] = $this->setUpUser('multi-chevaux');
        foreach ([[95, 'farrier', '-2 months'], [95, 'farrier', '-1 month'], [380, 'boarding', '-1 month']] as [$amount, $key, $when]) {
            Expense::forceCreate(['organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::where('key', $key)->value('id'),
                'spent_on' => now()->modify($when)->toDateString(), 'amount' => $amount, 'author_id' => $user->id, 'paid_on' => now()]);
        }
        CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => CareCategory::where('key', 'deworming')->value('id'), 'performed_at' => now()->subDays(40), 'author_id' => $user->id, 'next_check_on' => now()->addDays(50)]);
        $fake = new FakeClaude([
            ['tools' => [['find_records', ['kind' => 'expense_totals', 'from' => now()->startOfYear()->toDateString()]], ['find_records', ['kind' => 'last_care']]]],
            ['text' => 'Le maréchal vous a coûté 190 € cette année.'],
        ]);
        $this->app->instance(ClaudeGateway::class, $fake);
        $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Combien m\'a coûté le maréchal cette année ?'])->assertOk()->assertJson(['reply' => 'Le maréchal vous a coûté 190 € cette année.', 'actions' => []]);

        $results = collect($fake->calls[1]['messages'])->last()['content'];
        $totals = json_decode($results[0]['content'], true);
        $this->assertSame(570.0, (float) $totals['total']);
        $this->assertSame(190.0, (float) $totals['by_category']['Maréchal-ferrant']['total']);
        $last = json_decode($results[1]['content'], true);
        $this->assertSame('Vermifugation', $last[0]['category']);
        $this->assertSame(40, $last[0]['days_ago']);
        $this->assertStringContainsString('expense_totals', json_encode($fake->calls[0]['tools']));
    }

    public function test_vet_summary_shared_by_expiring_link_with_ai_synthesis(): void
    {
        [$owner, $horse] = $this->setUpUser();
        $fake = $this->fake();
        $fake->written = 'Abcès de pied le 3 octobre, traité par Equipalazone. Poids stable (510 kg).';
        CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => CareCategory::where('key', 'veterinary')->value('id'), 'performed_at' => now()->subWeek(), 'author_id' => $owner->id, 'diagnosis' => 'Abcès de pied']);
        CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => CareCategory::where('key', 'farrier')->value('id'), 'performed_at' => now()->subYear(), 'author_id' => $owner->id, 'reason' => 'Trop ancien']);
        HorseMeasurement::forceCreate(['horse_id' => $horse->id, 'measured_on' => now()->subMonths(2), 'weight_kg' => 520, 'author_id' => $owner->id]);
        HorseMeasurement::forceCreate(['horse_id' => $horse->id, 'measured_on' => now()->subDays(3), 'weight_kg' => 505, 'author_id' => $owner->id]);
        HealthObservation::forceCreate(['horse_id' => $horse->id, 'author_id' => $owner->id, 'observed_at' => now()->subDays(8), 'severity' => 'watch', 'body' => 'Boite à froid', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);

        $this->actingAs($owner)->get(route('horses.vet-summary', $horse))->assertOk()->assertSee('data-vet-share-form', false)->assertSee('Abcès de pied')->assertSee('-15,0 kg')->assertDontSee('Trop ancien');
        $this->actingAs($owner)->post(route('horses.vet-summary.store', $horse), ['days' => 7, 'recipient' => 'Dr Leroy', 'with_ai' => 1])->assertSessionHas('success');
        $share = HealthShare::sole();
        $this->assertSame(40, strlen($share->token));
        $this->assertStringContainsString('Abcès de pied', $fake->calls[0]['write']['prompt']);

        auth()->logout();
        $this->get('/resume/'.$share->token)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('pour Dr Leroy')->assertSee('data-vet-ai-summary', false)->assertSee('Poids stable')->assertSee('Boite à froid')->assertSee('Abcès de pied');
        $this->assertSame(1, $share->fresh()->views);

        // Expiré ou désactivé : plus rien.
        $this->travel(8)->days();
        $this->get('/resume/'.$share->token)->assertStatus(410)->assertDontSee('Abcès');
        $this->travelBack();
        $this->actingAs($owner)->post(route('horses.vet-summary.revoke', [$horse, $share]));
        $this->get('/resume/'.$share->token)->assertStatus(410);

        // Un cavalier en lecture : aperçu oui (s'il voit la santé), partage non.
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view', 'health.view']]);
        $this->actingAs($rider)->get(route('horses.vet-summary', $horse))->assertOk()->assertDontSee('data-vet-share-form', false);
        $this->actingAs($rider)->post(route('horses.vet-summary.store', $horse), ['days' => 7])->assertForbidden();
    }

    public function test_session_plan_is_proposed_then_kept_items_added(): void
    {
        [$user, $horse] = $this->setUpUser('multi-chevaux');
        $user->profile()->updateOrCreate([], ['riding_level' => 'Galop 4']);
        $fake = $this->fake();
        $lib = Exercise::where('scope', 'default')->whereNull('archived_at')->orderBy('id')->limit(2)->get();
        $fake->plan = ['summary' => 'Travail de la rectitude en 45 minutes.',
            'warmup' => [['name' => mb_strtoupper($lib[0]->name), 'minutes' => 10, 'note' => 'Pas actif']],
            'main' => [['name' => $lib[1]->name, 'minutes' => 25, 'note' => 'Sur les deux mains'], ['name' => 'Serpentine inventée', 'minutes' => 5, 'note' => 'Trois boucles']],
            'complementary' => [], 'cooldown' => [['name' => 'Pas rênes longues', 'minutes' => 5, 'note' => '']]];
        $session = app(\App\Services\RidingSessionService::class)->create($horse, $user, ['scheduled_at' => now()->addDay(), 'session_type' => 'free', 'planned_minutes' => 45, 'objective' => 'Rectitude']);

        $this->actingAs($user)->get(route('sessions.edit', $session))->assertSee('data-ai-plan', false);
        $json = $this->actingAs($user)->postJson(route('sessions.plan', $session))->assertOk()->json();
        $this->assertSame('Travail de la rectitude en 45 minutes.', $json['summary']);
        $this->assertSame([$lib[0]->id, true, 'warmup'], [$json['items'][0]['exercise_id'], $json['items'][0]['in_library'], $json['items'][0]['phase']]);
        $this->assertFalse($json['items'][2]['in_library']);
        $this->assertSame(45, $json['minutes']);
        $context = $fake->calls[0]['plan']['context'];
        $this->assertStringContainsString('Objectif : Rectitude', $context);
        $this->assertStringContainsString('galop 4', $context);

        // Ajout des exercices gardés (même formulaire que la bibliothèque).
        $this->actingAs($user)->post(route('sessions.exercises.store', $session), ['items' => [
            ['exercise_id' => $lib[0]->id, 'phase' => 'warmup', 'planned_minutes' => 10],
            ['exercise_id' => '', 'name' => 'Serpentine inventée', 'phase' => 'main', 'planned_minutes' => 5, 'instructions' => 'Trois boucles'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['warmup', 'main'], $session->fresh()->exercises->pluck('phase')->all());

        // Erreur de Claude : message clair, rien d'ajouté.
        $fake->plan = null;
        $this->actingAs($user)->postJson(route('sessions.plan', $session))->assertStatus(422)->assertJson(['error' => 'Le plan n\'a pas pu être préparé. Réessayez dans un instant.']);
    }

    public function test_weekly_digest_gets_an_ai_intro_only_for_assistant_plans(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-18 18:05'));
        [$user, $horse] = $this->setUpUser('multi-chevaux');
        $user->forceFill(['last_seen_at' => now()])->save();
        \App\Models\CalendarEvent::forceCreate(['organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Maréchal-ferrant', 'starts_at' => Carbon::parse('2026-10-20 09:00'), 'status' => 'planned', 'created_by' => $user->id]);
        $fake = $this->fake();
        $fake->written = 'Belle semaine en vue pour Uni : le maréchal passe mardi matin.';
        $this->artisan('reminders:weekly');
        Notification::assertSentTo($user, AppNotification::class, fn ($n) => $n->kind === 'weekly_digest' && str_starts_with($n->mailBody, 'Belle semaine en vue pour Uni') && str_contains($n->mailBody, 'Maréchal-ferrant') && str_contains($n->body, 'mardi matin'));

        // Claude indisponible : récapitulatif standard.
        $this->assertNull(app(WeeklyDigest::class)->aiIntro($user, ['events' => collect(), 'treatments' => collect(), 'payments' => collect(), 'debriefs' => collect()]));
        $fake->written = null;
        $d = app(WeeklyDigest::class)->for($user->fresh(['profile', 'memberships.role', 'memberships.organization']));
        $this->assertNull(app(WeeklyDigest::class)->aiIntro($user->fresh(['memberships.organization']), $d));
    }

    public function test_assistant_reply_is_spoken_with_elevenlabs(): void
    {
        [$user, $horse] = $this->setUpUser('multi-chevaux');
        config(['services.elevenlabs.key' => 'xi-test']);
        Http::fake(['api.elevenlabs.io/v1/text-to-speech/*' => Http::response('ID3-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg'])]);
        $this->app->instance(ClaudeGateway::class, new FakeClaude([['text' => 'C\'est noté : maréchal pour Uni mardi à 9 h.']]));
        $this->actingAs($user)->getJson('/assistant/etat')->assertJson(['speech' => true]);
        $id = $this->actingAs($user)->postJson('/assistant/consigne', ['text' => 'Le maréchal vient mardi à 9h', 'mode' => 'voice'])->json('request_id');

        $res = $this->actingAs($user)->post('/assistant/voix/'.$id)->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertSame('ID3-mp3-bytes', $res->getContent());
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/text-to-speech/'.\App\Services\Assistant\Speaker::DEFAULT_VOICE) && $r['text'] === 'C\'est noté : maréchal pour Uni mardi à 9 h.' && $r['language_code'] === 'fr');
        // Mis en cache : pas de second appel.
        $this->actingAs($user)->post('/assistant/voix/'.$id)->assertOk();
        Http::assertSentCount(1);
        // Réponse d'un autre utilisateur : refusé.
        $this->actingAs($this->user())->post('/assistant/voix/'.$id)->assertNotFound();
        // Désactivé par le super-administrateur.
        \App\Models\ApplicationSetting::put('assistant.speech_enabled', false);
        $this->actingAs($user)->getJson('/assistant/etat')->assertJson(['speech' => false]);
    }

    public function test_monitor_alerts_super_admins_when_cron_stops_or_errors_spike(): void
    {
        Notification::fake();
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        Cache::forever(\App\Services\NotificationDiagnostics::CRON_KEY, now()->subMinutes(40)->timestamp);
        $this->artisan('monitor:check')->expectsOutputToContain('cron');
        Notification::assertSentTo($admin, AppNotification::class, fn ($n) => $n->kind === 'system_alert' && str_contains($n->body, '40 minutes') && in_array('mail', $n->via($admin), true));
        // Pas de nouvelle alerte avant 6 heures.
        Notification::fake();
        $this->artisan('monitor:check');
        Notification::assertNothingSent();
        // Le cron repart : message de retour à la normale.
        Cache::forever(\App\Services\NotificationDiagnostics::CRON_KEY, now()->timestamp);
        $this->artisan('monitor:check');
        Notification::assertSentTo($admin, AppNotification::class, fn ($n) => str_contains($n->title, 'Cron rétabli'));

        // Erreurs en hausse.
        Notification::fake();
        \App\Models\ApplicationSetting::put('monitor_error_threshold', 3);
        foreach (range(1, 3) as $i) {
            SystemMonitor::recordError(new \RuntimeException('Échec n°'.$i));
        }
        $this->assertSame(3, SystemMonitor::errorsLastHour());
        $this->artisan('monitor:check');
        Notification::assertSentTo($admin, AppNotification::class, fn ($n) => str_contains($n->title, 'Erreurs en hausse') && str_contains($n->body, 'Échec n°3'));

        // Réglages dans l'administration.
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/parametres')->assertSee('data-monitor-settings', false)->assertSee('data-monitor-status', false);
    }

    public function test_permission_lookups_are_batched_per_request(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        foreach (range(1, 6) as $i) {
            $this->horseFor($user);
        }
        $access = app(HorseAccess::class);
        $access->flush();
        DB::enableQueryLog();
        $ids = $access->horseIdsWith($user, 'health.view');
        $access->horseIdsWith($user, 'sessions.view');
        $access->horseIdsWith($user, 'expenses.view');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(6, $ids);
        // Avant : ~3 requêtes par cheval et par appel (plus de 50 ici) ; maintenant une poignée, quel que soit le nombre de chevaux.
        $this->assertLessThan(15, $queries);
    }

    public function test_vet_summary_link_is_sent_by_email_or_sms_and_its_duration_changed(): void
    {
        Notification::fake();
        [$owner, $horse] = $this->setUpUser();
        $vet = \App\Models\Professional::forceCreate(['organization_id' => $horse->organization_id, 'last_name' => 'Leroy', 'first_name' => 'Anne', 'kind' => 'veterinarian', 'email' => 'cabinet@leroy.test', 'phone' => '06 11 22 33 44']);
        $horse->professionals()->attach($vet->id);

        $this->actingAs($owner)->get(route('horses.vet-summary', $horse))->assertSee('data-vet-contact', false)->assertSee('Anne Leroy – Vétérinaire')->assertSee('cabinet@leroy.test', false);
        $this->actingAs($owner)->post(route('horses.vet-summary.store', $horse), ['days' => 3, 'recipient' => 'Dr Anne Leroy', 'recipient_email' => 'cabinet@leroy.test', 'recipient_phone' => '06 11 22 33 44', 'send_email' => 1])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Envoyé par e-mail à cabinet@leroy.test'));
        $share = HealthShare::sole();
        $this->assertTrue($share->expires_at->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()));
        Notification::assertSentOnDemand(AppNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'cabinet@leroy.test'
            && $n->kind === 'vet_summary' && $n->url === $share->url() && in_array('mail', $channels, true));
        $this->assertNotNull($share->fresh()->emailed_at);

        // SMS pré-rempli avec le lien direct, et renvoi par e-mail à une autre adresse.
        $page = $this->actingAs($owner)->get(route('horses.vet-summary', $horse))->getContent();
        $this->assertStringContainsString('href="sms:0611223344?&body=', $page);
        $this->assertStringContainsString(rawurlencode($share->url()), $page);
        $this->actingAs($owner)->post(route('horses.vet-summary.email', [$horse, $share]), ['recipient_email' => 'secretariat@leroy.test'])->assertSessionHas('success');
        Notification::assertSentOnDemand(AppNotification::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'secretariat@leroy.test');

        // Durée d'ouverture modifiée (prolongation).
        $this->actingAs($owner)->put(route('horses.vet-summary.duration', [$horse, $share]), ['days' => 30])->assertSessionHas('success');
        $this->assertTrue($share->fresh()->expires_at->gt(now()->addDays(29)));
        $this->actingAs($owner)->put(route('horses.vet-summary.duration', [$horse, $share]), ['days' => 5])->assertSessionHasErrors('days');

        // Sans le droit de partager : ni envoi ni changement.
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view', 'health.view']]);
        $this->actingAs($rider)->post(route('horses.vet-summary.email', [$horse, $share]), ['recipient_email' => 'x@y.test'])->assertForbidden();
        $this->actingAs($rider)->put(route('horses.vet-summary.duration', [$horse, $share]), ['days' => 90])->assertForbidden();
    }
}
