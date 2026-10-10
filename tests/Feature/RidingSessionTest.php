<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\HorseAccessGrant;
use App\Models\RidingSession;
use App\Models\SessionExercise;
use App\Models\SessionTemplate;
use App\Support\Perm;
use Tests\TestCase;

class RidingSessionTest extends TestCase
{
    private function setUpSession(): array
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->format('Y-m-d H:i'), 'session_type' => 'dressage', 'objective' => 'Transitions', 'planned_minutes' => 45])->assertRedirect();

        return [$user, $horse, RidingSession::firstOrFail()];
    }

    public function test_full_session_lifecycle(): void
    {
        [$user, $horse, $session] = $this->setUpSession();
        $this->assertSame($user->id, $session->rider_id);
        $ex = Exercise::where('scope', 'default')->take(3)->get();

        $this->actingAs($user)->get("/seances/{$session->id}/preparer")->assertOk();
        $this->actingAs($user)->post("/seances/{$session->id}/exercices", ['items' => [
            ['exercise_id' => $ex[0]->id, 'phase' => 'warmup'], ['exercise_id' => $ex[1]->id, 'phase' => 'main'], ['exercise_id' => $ex[2]->id, 'phase' => 'main', 'planned_repetitions' => 8],
        ]])->assertRedirect();
        $this->assertSame(3, $session->items()->count());
        $item = $session->items()->where('exercise_id', $ex[1]->id)->first();
        $this->assertSame($ex[1]->name, $item->snapshot['name']);

        // Réordonnancement dans une phase
        $third = $session->items()->where('exercise_id', $ex[2]->id)->first();
        $this->actingAs($user)->post("/seances/{$session->id}/exercices/{$third->id}/deplacer", ['direction' => 'up']);
        $this->assertLessThan($item->fresh()->position, $third->fresh()->position);

        // Mode séance (JSON)
        $this->actingAs($user)->get("/seances/{$session->id}/en-cours")->assertOk();
        $this->assertSame('in_progress', $session->fresh()->status);
        $this->actingAs($user)->putJson("/seances/{$session->id}/exercices/{$item->id}", ['status' => 'done', 'done_repetitions' => 6, 'difficulty' => true])->assertOk();
        $this->assertSame('done', $item->fresh()->status);
        $this->assertNotNull($item->fresh()->status_changed_at);

        // Bilan et clôture
        $this->actingAs($user)->put("/seances/{$session->id}/bilan", ['actual_minutes' => 50, 'rider_feeling' => 4, 'horse_behavior' => 5, 'concentration' => 4, 'availability' => 4, 'progress' => 'Transitions plus nettes', 'to_rework' => 'Arrêts'])->assertRedirect("/seances/{$session->id}");
        $session->refresh();
        $this->assertTrue($session->isCompleted());
        $this->assertSame(50, $session->actual_minutes);

        // Historique : recherche par cheval, statistiques
        $this->actingAs($user)->get("/seances?horse={$horse->id}")->assertOk()->assertSee('Arrêts')->assertSee('90 derniers jours');

        // Séance terminée : exercices non modifiables
        $this->actingAs($user)->putJson("/seances/{$session->id}/exercices/{$item->id}", ['status' => 'skipped'])->assertStatus(422);
        $this->assertSame('done', $item->fresh()->status);
    }

    public function test_library_changes_do_not_rewrite_past_sessions(): void
    {
        [$user, $horse, $session] = $this->setUpSession();
        $this->actingAs($user)->post('/exercices', ['name' => 'Mon exercice', 'level' => 'all', 'instructions' => 'Version 1'])->assertRedirect();
        $exercise = Exercise::where('name', 'Mon exercice')->firstOrFail();
        $this->actingAs($user)->post("/seances/{$session->id}/exercices", ['items' => [['exercise_id' => $exercise->id]]]);
        $this->actingAs($user)->put("/seances/{$session->id}/bilan", ['actual_minutes' => 30]);

        $this->actingAs($user)->put("/exercices/{$exercise->id}", ['name' => 'Mon exercice renommé', 'level' => 'advanced', 'instructions' => 'Version 2'])->assertRedirect();
        $line = SessionExercise::where('riding_session_id', $session->id)->firstOrFail();
        $this->assertSame('Mon exercice', $line->name);
        $this->assertSame('Version 1', $line->snapshot['instructions']);
        $this->assertSame(1, $line->exercise_version);
        $this->assertSame(2, $exercise->fresh()->version);
    }

    public function test_duplicate_creates_new_planned_session_without_touching_original(): void
    {
        [$user, $horse, $session] = $this->setUpSession();
        $ex = Exercise::where('scope', 'default')->first();
        $this->actingAs($user)->post("/seances/{$session->id}/exercices", ['items' => [['exercise_id' => $ex->id]]]);
        $item = $session->items()->first();
        $item->update(['status' => 'done', 'done_repetitions' => 4]);
        $this->actingAs($user)->put("/seances/{$session->id}/bilan", ['actual_minutes' => 40, 'progress' => 'Original']);

        $this->actingAs($user)->post("/seances/{$session->id}/dupliquer")->assertRedirect();
        $this->assertSame(2, RidingSession::count());
        $copy = RidingSession::where('id', '!=', $session->id)->firstOrFail();
        $this->assertSame('planned', $copy->status);
        $this->assertNull($copy->progress);
        $this->assertSame('pending', $copy->items()->first()->status);
        $this->assertSame('Original', $session->fresh()->progress);
        $this->assertSame('done', $item->fresh()->status);
    }

    public function test_templates_create_independent_sessions(): void
    {
        [$user, $horse, $session] = $this->setUpSession();
        $ex = Exercise::where('scope', 'default')->take(2)->pluck('id');
        $this->actingAs($user)->post("/seances/{$session->id}/exercices", ['items' => [['exercise_id' => $ex[0]], ['exercise_id' => $ex[1]]]]);
        $this->actingAs($user)->post("/seances/{$session->id}/modele", ['name' => 'Routine dressage'])->assertRedirect();
        $template = SessionTemplate::firstOrFail();
        $this->actingAs($user)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'session_type' => 'free', 'template_id' => $template->id])->assertRedirect();
        $new = RidingSession::latest('id')->first();
        $this->assertSame(2, $new->items()->count());
        $this->assertSame($template->id, $new->template_id);
    }

    public function test_rider_can_only_edit_own_sessions(): void
    {
        [$owner, $horse, $session] = $this->setUpSession();
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => Perm::halfLeasePreset()]);
        $this->actingAs($rider)->get("/seances/{$session->id}")->assertOk();
        $this->actingAs($rider)->get("/seances/{$session->id}/preparer")->assertForbidden();
        $this->actingAs($rider)->post("/seances/{$session->id}/commentaires", ['body' => 'Très bien'])->assertRedirect();
        $this->assertDatabaseHas('session_comments', ['riding_session_id' => $session->id, 'author_id' => $rider->id]);
    }

    public function test_exercise_library_scopes_and_filters(): void
    {
        $user = $this->user();
        $other = $this->user();
        $this->actingAs($other)->post('/exercices', ['name' => 'Exercice privé', 'level' => 'all']);
        $this->actingAs($user)->get('/exercices?q=privé')->assertOk()->assertDontSee('Exercice privé');
        $default = Exercise::where('scope', 'default')->first();
        $this->actingAs($user)->get("/exercices/{$default->id}/modifier")->assertForbidden();
        $this->actingAs($user)->post("/exercices/{$default->id}/dupliquer")->assertRedirect();
        $this->assertDatabaseHas('exercise_library', ['scope' => 'personal', 'user_id' => $user->id, 'duplicated_from_id' => $default->id]);
        $this->actingAs($user)->get('/exercices?level=beginner&max_minutes=10')->assertOk();
    }
}
