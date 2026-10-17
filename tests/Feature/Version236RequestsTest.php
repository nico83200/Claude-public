<?php

namespace Tests\Feature;

use App\Models\RidingSession;
use Tests\TestCase;

/** Progression des séances : la durée prévue compte quand aucune durée réelle n'est saisie. */
class Version236RequestsTest extends TestCase
{
    public function test_work_time_uses_planned_minutes_when_no_actual_duration(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $mk = fn ($days, $planned, $actual, $status = 'completed') => RidingSession::forceCreate(['horse_id' => $horse->id, 'created_by' => $user->id, 'rider_id' => $user->id,
            'scheduled_at' => now()->subDays($days), 'session_type' => 'group_lesson', 'status' => $status, 'planned_minutes' => $planned, 'actual_minutes' => $actual]);
        $mk(1, 60, 60);
        $mk(3, 120, null); // terminée sans durée réelle : 120 min prévues
        $mk(7, null, 60);
        $mk(2, 45, null, 'planned'); // pas encore faite : ne compte pas

        $page = $this->actingAs($user)->get(route('sessions.index', ['horse' => $horse->id]))->assertOk();
        $this->assertSame(240, $page->viewData('stats')['minutes_90']);
        $this->assertSame(240, collect($page->viewData('stats')['by_week'])->sum('minutes'));
        $page->assertSee('4 h 0 min de travail');
    }
}
