<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\AssistantAction;
use App\Models\AssistantRequest;
use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Exercise;
use App\Models\FeedingPlan;
use App\Models\HorseMeasurement;
use App\Models\RidingSession;
use App\Services\Assistant\AssistantConfig;
use App\Services\Assistant\ClaudeGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FakeClaude;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    private function fake(array $script): FakeClaude
    {
        $fake = new FakeClaude($script);
        $this->app->instance(ClaudeGateway::class, $fake);

        return $fake;
    }

    /** Utilisateur dont l'offre inclut l'assistant, avec un cheval. */
    private function setUpUser(): array
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($user, null, ['official_name' => 'UNI DU BOIS', 'usual_name' => 'Uni', 'main_discipline' => 'dressage']);

        return [$user, $horse];
    }

    private function say($user, string $text, array $extra = [])
    {
        return $this->actingAs($user)->postJson('/assistant/consigne', ['text' => $text, 'mode' => 'voice'] + $extra);
    }

    public function test_dictated_appointment_is_created_directly_and_can_be_undone(): void
    {
        [$user, $horse] = $this->setUpUser();
        $fake = $this->fake([
            ['tools' => [['create_appointment', ['horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Maréchal', 'starts_at' => now()->year.'-12-02 09:00', 'all_day' => false, 'duration_minutes' => null, 'professional_id' => null, 'notes' => null]]]],
            ['text' => 'C\'est noté : maréchal pour Uni le 2 décembre à 9 h.'],
        ]);

        $res = $this->say($user, 'le maréchal est prévu le 2 décembre à 9h')->assertOk()->assertJson(['status' => 'done']);
        $event = CalendarEvent::where('horse_id', $horse->id)->where('type', 'farrier')->firstOrFail();
        $this->assertSame(now()->year.'-12-02 09:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertSame('C\'est noté : maréchal pour Uni le 2 décembre à 9 h.', $res->json('reply'));
        $this->assertCount(1, $res->json('actions'));
        $this->assertTrue($res->json('actions.0.undoable'));

        // Contexte transmis à Claude : date du jour, cheval accessible, règles, bibliothèque, outils stricts
        $system = collect($fake->calls[0]['system'])->pluck('text')->implode("\n");
        $this->assertStringContainsString((string) now()->year, $system);
        $this->assertStringContainsString($horse->id.' | Uni (UNI DU BOIS)', $system);
        $this->assertStringContainsString('année en cours', $system);
        $this->assertStringContainsString('Bibliothèque d\'exercices', $system);
        // Schémas acceptés par l'API : aucune union de types (anyOf / type multiple), champs requis existants
        $json = json_encode($fake->calls[0]['tools']);
        $this->assertStringNotContainsString('anyOf', $json);
        $this->assertDoesNotMatchRegularExpression('/"type":\[/', $json);
        $this->assertTrue(collect($fake->calls[0]['tools'])->every(fn ($t) => $t['inputSchema']['additionalProperties'] === false
            && array_diff($t['inputSchema']['required'], array_keys($t['inputSchema']['properties'])) === []));
        // Résultat de l'outil renvoyé à Claude au tour suivant
        $toolResult = $fake->calls[1]['messages'][2]['content'][0];
        $this->assertSame('tool_result', $toolResult['type']);
        $this->assertFalse($toolResult['isError']);

        $req = AssistantRequest::firstOrFail();
        $this->assertSame('le maréchal est prévu le 2 décembre à 9h', $req->input_text);
        $this->assertSame('voice', $req->input_mode);

        // Annulation
        $this->actingAs($user)->postJson($res->json('actions.0.undo_url'))->assertOk();
        $this->assertSoftDeleted($event);
        $this->actingAs($user)->postJson($res->json('actions.0.undo_url'))->assertStatus(422);
    }

    public function test_session_is_planned_today_with_coherent_exercises(): void
    {
        [$user, $horse] = $this->setUpUser();
        $ex = Exercise::where('scope', 'default')->take(3)->get();
        $this->fake([
            ['tools' => [['create_session', [
                'horse_id' => $horse->id, 'scheduled_at' => now()->format('Y-m-d').' 18:00', 'session_type' => 'free', 'discipline' => 'dressage',
                'objective' => 'Rectitude', 'planned_minutes' => 55, 'location' => null,
                'exercises' => [
                    ['exercise_id' => $ex[0]->id, 'name' => null, 'phase' => 'warmup', 'minutes' => 12, 'repetitions' => null, 'instructions' => null],
                    ['exercise_id' => $ex[1]->id, 'name' => null, 'phase' => 'main', 'minutes' => 20, 'repetitions' => null, 'instructions' => null],
                    ['exercise_id' => null, 'name' => 'Lignes droites sur la piste intérieure', 'phase' => 'main', 'minutes' => 15, 'repetitions' => 4, 'instructions' => 'Regarder loin devant'],
                    ['exercise_id' => 999999, 'name' => null, 'phase' => 'cooldown', 'minutes' => 8, 'repetitions' => null, 'instructions' => null],
                ],
            ]]]],
            ['text' => 'Séance de monte libre en dressage ajoutée pour aujourd\'hui.'],
        ]);

        $this->say($user, 'réalise une séance de monte libre en dressage pour travailler la rectitude du cheval', ['horse_id' => $horse->id])->assertOk();
        $session = RidingSession::firstOrFail();
        $this->assertTrue($session->scheduled_at->isToday());
        $this->assertSame(['free', 'dressage', 'Rectitude', 55], [$session->session_type, $session->discipline, $session->objective, $session->planned_minutes]);
        $items = $session->items()->orderBy('position')->get();
        $this->assertCount(3, $items); // l'identifiant inconnu sans nom est ignoré
        $this->assertSame($ex[0]->name, $items[0]->name);
        $this->assertSame('Lignes droites sur la piste intérieure', $items[2]->name);
        $this->assertNull($items[2]->exercise_id);
        $this->assertDatabaseHas('calendar_events', ['source_id' => $session->id, 'type' => 'session']);
    }

    public function test_tools_respect_permissions(): void
    {
        [$user] = $this->setUpUser();
        $other = $this->user();
        $foreign = $this->horseFor($other);
        $fake = $this->fake([
            ['tools' => [['create_appointment', ['horse_id' => $foreign->id, 'type' => 'farrier', 'title' => 'Maréchal', 'starts_at' => '2026-12-02 09:00', 'all_day' => false, 'duration_minutes' => null, 'professional_id' => null, 'notes' => null]]]],
            ['text' => 'Je n\'ai pas accès à ce cheval.'],
        ]);
        $this->say($user, 'maréchal pour le cheval du voisin')->assertOk()->assertJson(['actions' => []]);
        $this->assertSame(0, CalendarEvent::count());
        $result = $fake->calls[1]['messages'][2]['content'][0];
        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('introuvable', $result['content']);
        // Le cheval d'un autre n'apparaît pas dans le contexte
        $this->assertStringNotContainsString($foreign->official_name, collect($fake->calls[0]['system'])->pluck('text')->implode("\n"));
    }

    public function test_feature_and_quota_are_enforced(): void
    {
        $free = $this->user();
        $this->fake([]);
        $this->say($free, 'bonjour')->assertStatus(403)->assertJsonFragment(['message' => 'L\'assistant vocal n\'est pas inclus dans l\'offre de l\'espace '.$free->personalOrganization()->name.'.']);

        [$user] = $this->setUpUser();
        $user->personalOrganization()->licenses()->latest('id')->first()->update(['limit_overrides' => ['assistant_monthly' => 1]]);
        $this->fake([['text' => 'Bonjour !'], ['text' => 'Encore']]);
        $this->say($user, 'bonjour')->assertOk()->assertJson(['remaining' => 0]);
        $this->say($user, 'encore')->assertStatus(403);
        $this->actingAs($user)->getJson('/assistant/etat')->assertOk()->assertJson(['available' => false]);
    }

    public function test_assistant_asks_which_horse_then_continues_the_conversation(): void
    {
        [$user, $horse] = $this->setUpUser();
        $second = $this->horseFor($user, null, ['official_name' => 'OLYMPE']);
        $fake = $this->fake([
            ['tools' => [['ask_user', ['question' => 'Pour quel cheval ?', 'options' => ['Uni', 'OLYMPE']]]]],
            ['tools' => [['create_appointment', ['horse_id' => $second->id, 'type' => 'veterinary', 'title' => 'Vaccin', 'starts_at' => '2026-11-05 00:00', 'all_day' => true, 'duration_minutes' => null, 'professional_id' => null, 'notes' => null]]]],
            ['text' => 'Vaccin noté pour Olympe le 5 novembre.'],
        ]);
        $first = $this->say($user, 'vaccin le 5 novembre')->assertOk()->assertJson(['status' => 'clarify', 'question' => ['question' => 'Pour quel cheval ?', 'options' => ['Uni', 'OLYMPE']]]);
        $this->assertSame(0, CalendarEvent::count());

        $this->say($user, 'OLYMPE', ['conversation_id' => $first->json('conversation_id')])->assertOk()->assertJson(['status' => 'done']);
        $this->assertSame(1, CalendarEvent::where('horse_id', $second->id)->count());
        // L'échange précédent est renvoyé en texte
        $msgs = $fake->calls[1]['messages'];
        $this->assertSame('vaccin le 5 novembre', $msgs[0]['content']);
        $this->assertSame('Pour quel cheval ?', $msgs[1]['content']);
        $this->assertSame('OLYMPE', $msgs[2]['content']);
    }

    public function test_recorded_care_uses_follow_up_rule_and_undo_restores_previous_state(): void
    {
        [$user, $horse] = $this->setUpUser();
        $farrier = CareCategory::where('key', 'farrier')->first();
        $previous = CareRecord::forceCreate(['uuid' => (string) Str::uuid(), 'horse_id' => $horse->id, 'care_category_id' => $farrier->id, 'performed_at' => now()->subWeeks(6), 'next_check_on' => now()->addDay()->toDateString()]);
        $this->fake([
            ['tools' => [['record_care', ['horse_id' => $horse->id, 'category' => 'farrier', 'performed_at' => now()->format('Y-m-d').' 09:00', 'professional_id' => null, 'reason' => null, 'care_performed' => 'Parage', 'cost' => 85, 'add_expense' => true,
                'next_interval_value' => null, 'next_interval_unit' => null, 'next_check_on' => null, 'remember_frequency' => false]]]],
            ['text' => 'Passage du maréchal noté, prochain dans 6 semaines.'],
        ]);
        $res = $this->say($user, 'le maréchal est passé ce matin, 85 euros')->assertOk();
        $care = CareRecord::latest('id')->first();
        $this->assertSame(now()->addWeeks(6)->toDateString(), $care->next_check_on->toDateString());
        $this->assertNotNull($previous->fresh()->next_check_done_at);
        $this->assertDatabaseHas('expenses', ['care_record_id' => $care->id, 'amount' => 85]);

        $this->actingAs($user)->postJson($res->json('actions.0.undo_url'))->assertOk();
        $this->assertSoftDeleted($care);
        $this->assertNull($previous->fresh()->next_check_done_at);
        $this->assertSoftDeleted('expenses', ['care_record_id' => $care->id]);
        $this->assertSame(0, CalendarEvent::where('source_id', $care->id)->where('source_type', $care->getMorphClass())->count());
    }

    public function test_feeding_change_creates_new_version_and_undo_restores(): void
    {
        [$user, $horse] = $this->setUpUser();
        $old = FeedingPlan::create(['horse_id' => $horse->id, 'name' => 'Ration hiver', 'starts_on' => now()->subMonth()->toDateString()]);
        $old->entries()->create(['feed' => 'Granulés', 'quantity' => 1.5, 'unit' => 'kg', 'time_of_day' => '18:00:00']);
        $this->fake([
            ['tools' => [['set_feeding', ['horse_id' => $horse->id, 'changes' => [
                ['feed' => 'granulés', 'quantity' => 2, 'unit' => 'kg', 'time_of_day' => '18:00', 'frequency' => null, 'is_supplement' => false, 'note' => null, 'remove' => false],
                ['feed' => 'Huile de lin', 'quantity' => 0.05, 'unit' => 'L', 'time_of_day' => null, 'frequency' => null, 'is_supplement' => true, 'note' => null, 'remove' => false],
            ]]]]],
            ['text' => 'Ration mise à jour.'],
        ]);
        $res = $this->say($user, 'Uni passe à 2 kg de granulés le soir et ajoute de l\'huile de lin')->assertOk();
        $new = FeedingPlan::where('horse_id', $horse->id)->latest('id')->first();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(now()->subDay()->toDateString(), $old->fresh()->ends_on->toDateString());
        $this->assertEquals(['Granulés' => 2.0, 'Huile de lin' => 0.05], $new->entries->mapWithKeys(fn ($e) => [$e->feed => (float) $e->quantity])->sortKeys()->all());

        $this->actingAs($user)->postJson($res->json('actions.0.undo_url'))->assertOk();
        $this->assertNull(FeedingPlan::find($new->id));
        $this->assertNull($old->fresh()->ends_on);
    }

    public function test_questions_use_only_accessible_records(): void
    {
        [$user, $horse] = $this->setUpUser();
        $other = $this->user();
        $foreign = $this->horseFor($other);
        foreach ([$horse, $foreign] as $h) {
            $e = new CalendarEvent(['horse_id' => $h->id, 'type' => 'farrier', 'title' => 'Maréchal '.$h->id, 'starts_at' => now()->addWeek()]);
            $e->organization_id = $h->organization_id;
            $e->save();
        }
        $fake = $this->fake([
            ['tools' => [['find_records', ['kind' => 'appointments', 'horse_id' => null, 'from' => null, 'to' => null, 'limit' => null]]]],
            ['text' => 'Le prochain maréchal d\'Uni est dans une semaine.'],
        ]);
        $this->say($user, 'quand est le prochain maréchal ?')->assertOk()->assertJson(['actions' => []]);
        $found = json_decode($fake->calls[1]['messages'][2]['content'][0]['content'], true);
        $this->assertCount(1, $found);
        $this->assertSame('Maréchal '.$horse->id, $found[0]['title']);
    }

    public function test_only_author_can_undo_and_history_lists_requests(): void
    {
        [$user, $horse] = $this->setUpUser();
        $this->fake([
            ['tools' => [['add_observation', ['horse_id' => $horse->id, 'body' => 'Légère boiterie antérieur gauche', 'severity' => 'watch']]]],
            ['text' => 'Observation notée.'],
        ]);
        $res = $this->say($user, 'Uni boite un peu de l\'antérieur gauche')->assertOk();
        $action = AssistantAction::firstOrFail();
        $this->actingAs($this->user())->postJson($res->json('actions.0.undo_url'))->assertStatus(422);
        $this->actingAs($user)->get('/assistant')->assertOk()->assertSee('Uni boite un peu')->assertSee($action->summary);
        // Délai de 24 h dépassé
        $this->travel(25)->hours();
        $this->actingAs($user)->postJson($res->json('actions.0.undo_url'))->assertStatus(422);
    }

    public function test_transcription_goes_to_elevenlabs_and_audio_is_not_kept(): void
    {
        [$user] = $this->setUpUser();
        $this->fake([]);
        config(['services.elevenlabs.key' => 'el_test']);
        Http::fake(['api.elevenlabs.io/*' => Http::response(['text' => 'le maréchal est prévu le 2 décembre à 9h', 'language_code' => 'fra'])]);
        $audio = UploadedFile::fake()->createWithContent('consigne.webm', str_repeat('a', 2048));
        $this->actingAs($user)->post('/assistant/transcription', ['audio' => $audio], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['text' => 'le maréchal est prévu le 2 décembre à 9h']);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.elevenlabs.io/v1/speech-to-text' && $r->hasHeader('xi-api-key', 'el_test'));
        $this->assertSame([], Storage::disk('local')->allFiles('assistant'));

        config(['services.elevenlabs.key' => null]);
        $this->actingAs($user)->post('/assistant/transcription', ['audio' => UploadedFile::fake()->createWithContent('c.webm', 'abc')], ['Accept' => 'application/json'])->assertStatus(502);
    }

    public function test_super_admin_configures_instructions_model_and_keys(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->user())->withSession(['auth.password_confirmed_at' => time()])->get('/admin/assistant')->assertNotFound();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/assistant')->assertOk()->assertSee('Indications pour l');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put('/admin/assistant', [
            'enabled' => '1', 'model' => 'claude-sonnet-5-5', 'effort' => 'low', 'session_min' => 40, 'session_max' => 45,
            'instructions' => '« Le carré » désigne la carrière de dressage.', 'anthropic_key' => 'sk-ant-secret-1234', 'elevenlabs_key' => 'el-secret-9876',
        ])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('sk-ant-secret', json_encode(ApplicationSetting::pluck('value')));
        $this->assertSame('sk-ant-secret-1234', config('services.anthropic.key'));
        $this->assertSame('el-secret-9876', config('services.elevenlabs.key'));

        [$user, $horse] = $this->setUpUser();
        $fake = $this->fake([['text' => 'OK']]);
        $this->say($user, 'bonjour')->assertOk();
        $system = collect($fake->calls[0]['system'])->pluck('text')->implode("\n");
        $this->assertStringContainsString('« Le carré » désigne la carrière de dressage.', $system);
        $this->assertStringContainsString('entre 40 et 45 minutes', $system);
        $this->assertSame('claude-sonnet-5-5', app(AssistantConfig::class)->model());

        // Désactivation globale
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put('/admin/assistant', ['model' => 'claude-opus-5-5', 'effort' => 'medium', 'session_min' => 50, 'session_max' => 60])->assertSessionHasNoErrors();
        $this->say($user, 'bonjour')->assertStatus(403)->assertJsonFragment(['message' => 'L\'assistant est désactivé sur cette instance.']);
    }

    public function test_optional_fields_can_be_omitted_by_claude(): void
    {
        [$user, $horse] = $this->setUpUser();
        $fake = $this->fake([
            ['tools' => [
                ['create_appointment', ['horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Maréchal', 'starts_at' => '2026-12-02 09:00']],
                ['set_feeding', ['horse_id' => $horse->id, 'changes' => [['feed' => 'Foin', 'quantity' => 8, 'unit' => 'kg']]]],
                ['create_session', ['horse_id' => $horse->id, 'scheduled_at' => '2026-12-03 18:00', 'session_type' => 'free', 'planned_minutes' => 55, 'exercises' => [['name' => 'Pas rênes longues', 'phase' => 'warmup']]]],
                ['create_appointment', ['horse_id' => $horse->id, 'type' => 'farrier']], // champs obligatoires manquants
            ]],
            ['text' => 'Fait.'],
        ]);
        $res = $this->say($user, 'maréchal le 2 décembre à 9 h, 8 kg de foin, séance le 3')->assertOk();
        $this->assertCount(3, $res->json('actions'));
        $this->assertFalse(CalendarEvent::where('type', 'farrier')->first()->all_day);
        $results = collect($fake->calls[1]['messages'][2]['content']);
        $this->assertTrue($results[3]['isError']);
        $this->assertStringContainsString('Champ obligatoire manquant', $results[3]['content']);
    }

    public function test_dictated_weight_with_tape_measures_is_estimated_and_undoable(): void
    {
        [$user, $horse] = $this->setUpUser();
        $this->fake([
            ['tools' => [['record_weight', ['horse_id' => $horse->id, 'girth_cm' => 190, 'length_cm' => 173, 'body_condition' => 5]]]],
            ['text' => 'C\'est noté : environ 526 kg pour Uni.'],
        ]);
        $res = $this->say($user, 'Uni fait 190 de tour de poitrine et 173 de long, état 5')->assertOk();
        $m = HorseMeasurement::firstOrFail();
        $this->assertSame(525.8, $m->weight_kg);
        $this->assertSame('tape', $m->method);
        $this->assertStringContainsString('526 kg (estimé au ruban)', $res->json('actions.0.summary'));

        $this->actingAs($user)->postJson($res->json('actions.0.undo_url'))->assertOk();
        $this->assertSame(0, HorseMeasurement::count());
    }
}
