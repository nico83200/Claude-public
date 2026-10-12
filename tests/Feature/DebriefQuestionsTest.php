<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Notifications\AppNotification;
use App\Services\RidingSessionService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Bilan de séance en questions à choix, adaptées aux exercices et à l'objectif. */
class DebriefQuestionsTest extends TestCase
{
    public function test_debrief_questions_follow_the_session_and_fill_the_report(): void
    {
        Notification::fake();
        $owner = $this->user();
        $rider = $this->user();
        $horse = $this->horseFor($owner);
        // Le cavalier (demi-pensionnaire) fait le bilan ; le propriétaire est prévenu d'une anomalie.
        \App\Models\HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view', 'sessions.view', 'sessions.edit_all']]);
        $ex = Exercise::where('scope', 'default')->whereHas('category', fn ($q) => $q->where('name', 'Mobilité latérale'))->firstOrFail();
        $session = app(RidingSessionService::class)->create($horse, $owner, ['scheduled_at' => now()->addHour(), 'session_type' => 'dressage', 'objective' => 'Cession à la jambe sur les deux mains', 'planned_minutes' => 45],
            [['exercise_id' => $ex->id, 'phase' => 'main']]);

        $page = $this->actingAs($rider)->get(route('sessions.debrief', $session))->assertOk()
            ->assertSee('data-debrief-questions', false)
            ->assertSee('L\'objectif « Cession à la jambe sur les deux mains » est-il atteint ?', false)
            ->assertSee($ex->name)->assertSee('Mobilité latérale')->assertSee('Incurvation')
            ->assertDontSee('name="difficulties"', false);

        $this->actingAs($rider)->put(route('sessions.debrief.save', $session), [
            'questionnaire' => '1', 'actual_minutes' => 40, 'rider_feeling' => 4, 'horse_behavior' => 4, 'concentration' => 3, 'availability' => 4,
            'q_objective' => 'en partie', 'q_good' => ['Contact'], 'q_hard' => [$ex->name], 'q_rework' => [$ex->name, 'Incurvation'],
            'q_anomalies' => ['Raideur'], 'q_next' => ['Plus de détente'], 'q_comment' => 'Cheval froid au début',
        ])->assertRedirect();
        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertSame('Objectif « Cession à la jambe sur les deux mains » : en partie. Bien réussi : Contact. Remarque : Cheval froid au début', $session->progress);
        $this->assertSame([$ex->name, $ex->name.', Incurvation', 'Raideur', 'Plus de détente'], [$session->difficulties, $session->to_rework, $session->anomalies, $session->next_objectives]);
        // Le propriétaire est prévenu de l'anomalie et du bilan, avec le lien pour le consulter.
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => $n->kind === 'session_report' && $n->url === route('sessions.show', $session)
            && str_contains($n->body, $rider->name.' a fait le bilan') && str_contains($n->body, 'ressenti 4/5'));
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => str_contains($n->title, 'Anomalie') && str_contains($n->body, 'Raideur'));
        Notification::assertNotSentTo($rider, AppNotification::class, fn ($n) => $n->kind === 'session_report');
        $this->actingAs($owner)->get(route('sessions.show', $session))->assertOk()->assertSee('Bien réussi : Contact');

        // Réouverture : les réponses sont retrouvées ; « Rien à signaler » n'enregistre aucune anomalie.
        $this->actingAs($owner)->get(route('sessions.debrief', $session))->assertOk()->assertSee('value="en partie" class="sr-only" checked', false);
        $this->actingAs($owner)->put(route('sessions.debrief.save', $session), ['questionnaire' => '1', 'q_hard' => ['Rien de particulier'], 'q_anomalies' => ['Rien à signaler']]);
        $this->assertNull($session->fresh()->anomalies);
        $this->assertNull($session->fresh()->difficulties);
    }

    public function test_owner_debriefing_their_own_session_is_not_notified(): void
    {
        Notification::fake();
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $session = app(RidingSessionService::class)->create($horse, $owner, ['scheduled_at' => now()->addHour(), 'session_type' => 'free', 'planned_minutes' => 30]);
        $this->actingAs($owner)->put(route('sessions.debrief.save', $session), ['questionnaire' => '1', 'q_good' => ['Rythme'], 'q_anomalies' => ['Rien à signaler']])->assertRedirect();
        Notification::assertNotSentTo($owner, AppNotification::class);
    }
}
