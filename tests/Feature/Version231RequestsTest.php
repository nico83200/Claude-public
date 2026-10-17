<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\HorseAccessGrant;
use App\Services\CareCalendarSync;
use App\Support\Perm;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Calendrier sur téléphone : haut fixe, liste qui défile, glissés supprimer / valider. */
class Version231RequestsTest extends TestCase
{
    private function event($horse, $user, array $extra = []): CalendarEvent
    {
        return CalendarEvent::forceCreate($extra + ['uuid' => (string) Str::uuid(), 'organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'created_by' => $user->id,
            'type' => 'competition', 'title' => 'Concours', 'starts_at' => now()->subDay()->setTime(10, 0), 'status' => 'planned']);
    }

    /** Configuration du glissé de la ligne d'un événement : [peut supprimer, peut valider], ou null sans glissé. */
    private function swipe(string $html, CalendarEvent $event): ?array
    {
        if (! preg_match('#<div[^>]*data-swipe-event="'.$event->id.'"[^>]*>#', $html, $m)) {
            return null;
        }
        $tag = html_entity_decode($m[0]);
        if (! str_contains($tag, 'data-swipe-row')) {
            return null;
        }

        return [! str_contains($tag, 'deleteUrl: null'), ! str_contains($tag, 'validateUrl: null')];
    }

    public function test_mobile_layout_keeps_the_top_fixed_and_scrolls_the_list(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->event($horse, $user);
        $this->actingAs($user)->get(route('calendar.index'))->assertOk()->assertSee('data-calendar-top', false)
            ->assertSee('x-data="agendaScroll"', false)->assertSee('data-calendar-today', false)->assertSee('data-today-divider', false);
    }

    public function test_swipe_left_deletes_with_undo_and_swipe_right_validates(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $past = $this->event($horse, $user, ['title' => 'Concours passé']);
        $future = $this->event($horse, $user, ['title' => 'Concours à venir', 'starts_at' => now()->addDays(2)]);
        $recurring = $this->event($horse, $user, ['title' => 'Paddock', 'recurrence' => 'weekly', 'starts_at' => now()->subDays(2)]);

        $html = $this->actingAs($user)->get(route('calendar.index'))->assertOk()->getContent();
        $this->assertSame([true, true], $this->swipe($html, $past), 'Passé et prévu : supprimer ou valider.');
        $this->assertSame([true, false], $this->swipe($html, $future), 'À venir : pas de validation.');
        $this->assertSame([false, true], $this->swipe($html, $recurring), 'Récurrent : choix de suppression (voir 2.32) et validation de l\'occurrence.');

        // Valider (glissé à droite).
        $this->actingAs($user)->postJson(route('calendar.done', $past))->assertOk()->assertJson(['text' => '« Concours passé » validé']);
        $this->assertSame('done', $past->fresh()->status);
        $this->actingAs($user)->postJson(route('calendar.done', $past))->assertStatus(422);
        $this->actingAs($user)->postJson(route('calendar.done', $recurring))->assertStatus(422); // occurrence (date) requise

        // Supprimer (glissé à gauche) puis « Annuler ».
        $res = $this->actingAs($user)->deleteJson(route('calendar.destroy', $future))->assertOk()->json();
        $this->assertSoftDeleted($future);
        $this->actingAs($user)->postJson($res['restore_url'])->assertOk();
        $this->assertNotSoftDeleted($future);
    }

    public function test_no_swipe_without_rights_nor_delete_for_health_linked_events(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $care = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => CareCategory::where('key', 'farrier')->value('id'), 'author_id' => $user->id,
            'performed_at' => now()->subWeeks(6), 'next_check_on' => now()->addDays(3)->toDateString()]);
        $check = app(CareCalendarSync::class)->planCheck($care);
        $html = $this->actingAs($user)->get(route('calendar.index'))->getContent();
        $this->assertNull($this->swipe($html, $check), 'Contrôle lié au carnet : suppression depuis la fiche seulement.');

        $past = $this->event($horse, $user);
        $reader = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $reader->id, 'permissions' => [Perm::HORSE_VIEW, Perm::CALENDAR_VIEW]]);
        $html = $this->actingAs($reader)->get(route('calendar.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-swipe-row', $html);
        $this->actingAs($reader)->postJson(route('calendar.done', $past))->assertForbidden();
        $this->actingAs($reader)->deleteJson(route('calendar.destroy', $past))->assertForbidden();
    }
}
