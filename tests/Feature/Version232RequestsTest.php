<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Services\CalendarFeed;
use App\Services\Entitlements;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Séries récurrentes : valider ou supprimer une occurrence, la suite, ou toute la série. */
class Version232RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Paddock chaque lundi à 10 h depuis le 5 octobre 2026 (on est le vendredi 16). */
    private function series(): array
    {
        Carbon::setTestNow('2026-10-16 12:00');
        $user = $this->user();
        $horse = $this->horseFor($user);
        $event = CalendarEvent::forceCreate(['uuid' => (string) Str::uuid(), 'organization_id' => $horse->organization_id, 'horse_id' => $horse->id, 'created_by' => $user->id,
            'type' => 'competition', 'title' => 'Paddock', 'starts_at' => Carbon::parse('2026-10-05 10:00'), 'status' => 'planned', 'recurrence' => 'weekly']);

        return [$user, $horse, $event];
    }

    private function dates(CalendarEvent $event): array
    {
        return $event->fresh()->occurrencesBetween(Carbon::parse('2026-10-01'), Carbon::parse('2026-11-30 23:59'))->map->toDateString()->all();
    }

    public function test_delete_one_occurrence_with_undo(): void
    {
        [$user, , $event] = $this->series();
        $res = $this->actingAs($user)->deleteJson(route('calendar.destroy', [$event, 'scope' => 'occurrence', 'date' => '2026-10-19']))->assertOk()
            ->assertJson(['text' => 'Occurrence du 19/10 supprimée'])->json();
        $this->assertNotSoftDeleted($event);
        $this->assertNotContains('2026-10-19', $this->dates($event));
        $this->assertContains('2026-10-26', $this->dates($event));
        $this->actingAs($user)->get(route('calendar.index', ['date' => '2026-10-19', 'view' => 'week']))->assertOk();

        $this->actingAs($user)->postJson($res['restore_url'])->assertOk();
        $this->assertContains('2026-10-19', $this->dates($event));

        // Une date hors série est refusée.
        $this->actingAs($user)->deleteJson(route('calendar.destroy', [$event, 'scope' => 'occurrence', 'date' => '2026-10-20']))->assertStatus(422);
        $this->assertNotSoftDeleted($event);
    }

    public function test_delete_following_ends_the_series_at_the_previous_occurrence(): void
    {
        [$user, , $event] = $this->series();
        $res = $this->actingAs($user)->deleteJson(route('calendar.destroy', [$event, 'scope' => 'following', 'date' => '2026-10-19']))->assertOk()
            ->assertJson(['text' => 'Série « Paddock » arrêtée au 12/10/2026', 'reload' => true])->json();
        $this->assertSame('2026-10-12', $event->fresh()->recurrence_until->toDateString());
        $this->assertSame(['2026-10-05', '2026-10-12'], $this->dates($event));

        // « Annuler » : la série reprend sans fin.
        $this->actingAs($user)->postJson($res['restore_url'])->assertOk();
        $this->assertNull($event->fresh()->recurrence_until);

        // Depuis la première occurrence : toute la série disparaît.
        $this->actingAs($user)->delete(route('calendar.destroy', [$event, 'scope' => 'following', 'date' => '2026-10-05']))->assertRedirect(route('calendar.index'));
        $this->assertSoftDeleted($event);
    }

    public function test_delete_the_whole_series(): void
    {
        [$user, , $event] = $this->series();
        $res = $this->actingAs($user)->deleteJson(route('calendar.destroy', [$event, 'scope' => 'series']))->assertOk()->assertJson(['text' => 'Série « Paddock » supprimée'])->json();
        $this->assertSoftDeleted($event);
        $this->actingAs($user)->postJson($res['restore_url'])->assertOk();
        $this->assertNotSoftDeleted($event);
    }

    public function test_validate_a_past_occurrence_detaches_it(): void
    {
        [$user, , $event] = $this->series();
        $this->actingAs($user)->postJson(route('calendar.done', [$event, 'date' => '2026-10-19']))->assertStatus(422); // à venir
        $this->actingAs($user)->postJson(route('calendar.done', [$event, 'date' => '2026-10-12']))->assertOk()->assertJson(['text' => '« Paddock » du 12/10 validé']);

        $done = CalendarEvent::where('recurrence_parent_id', $event->id)->sole();
        $this->assertSame('2026-10-12 10:00', $done->starts_at->format('Y-m-d H:i'));
        $this->assertSame('done', $done->status);
        $this->assertNull($done->recurrence);
        $this->assertNotSame($event->uuid, $done->uuid);
        $this->assertSame('planned', $event->fresh()->status);
        $this->assertSame(['2026-10-05', '2026-10-19', '2026-10-26', '2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23', '2026-11-30'], $this->dates($event));

        // Agenda : l'occurrence validée apparaît une seule fois, comme événement réalisé.
        $html = $this->actingAs($user)->get(route('calendar.index', ['date' => '2026-10-12', 'view' => 'week']))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-swipe-event="'.$done->id.'"'));

        // Depuis la fiche de l'occurrence : on arrive sur l'événement validé.
        $this->actingAs($user)->post(route('calendar.done', [$event, 'date' => '2026-10-05']))->assertRedirect();
        $this->assertSame(2, CalendarEvent::where('recurrence_parent_id', $event->id)->count());
    }

    public function test_list_rows_and_occurrence_page_offer_the_three_choices(): void
    {
        [$user, , $event] = $this->series();
        $html = $this->actingAs($user)->get(route('calendar.index', ['date' => '2026-10-16', 'view' => 'month']))->assertOk()->getContent();
        $this->assertStringContainsString('data-swipe-choice="occurrence"', $html);
        $this->assertStringContainsString('data-swipe-choice="following"', $html);
        $this->assertStringContainsString('data-swipe-choice="series"', $html);
        $this->assertStringContainsString('href="'.route('calendar.show', [$event, 'date' => '2026-10-12']).'"', $html);
        $this->assertStringContainsString(e(route('calendar.done', [$event, 'date' => '2026-10-12'])), str_replace('\/', '/', $html));

        // Fiche d'une occurrence passée : valider cette occurrence, et le choix de suppression.
        $this->actingAs($user)->get(route('calendar.show', [$event, 'date' => '2026-10-12']))->assertOk()
            ->assertSee('Valider cette occurrence')->assertSee('lundi 12 octobre 2026')->assertSee('data-occurrence-delete', false)
            ->assertSee('Cette occurrence et les suivantes')->assertSee('Toute la série');
        // Occurrence à venir : pas encore validable. Date hors série : la série entière.
        $this->actingAs($user)->get(route('calendar.show', [$event, 'date' => '2026-10-19']))->assertOk()->assertDontSee('Valider cette occurrence');
        $this->actingAs($user)->get(route('calendar.show', [$event, 'date' => '2026-10-20']))->assertOk()->assertDontSee('data-occurrence-delete', false)
            ->assertSee('Supprimer toute la série');
    }

    public function test_excluded_dates_are_published_in_the_calendar_feed(): void
    {
        [$user, , $event] = $this->series();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        app()->forgetInstance(Entitlements::class);
        $event->exclude('2026-10-19');
        $event->save();
        $ics = app(CalendarFeed::class)->render($user->fresh());
        $this->assertMatchesRegularExpression('/RRULE:[^\r\n]+\r?\nEXDATE:20261019T\d{6}Z/', $ics);
    }
}
