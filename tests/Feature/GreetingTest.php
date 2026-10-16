<?php

namespace Tests\Feature;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Support\Greeting;
use Carbon\Carbon;
use Tests\TestCase;

/** Salutation du tableau de bord : varie selon le moment et le jour, stable dans un créneau. */
class GreetingTest extends TestCase
{
    public function test_greeting_follows_time_and_weekday(): void
    {
        $monday = Carbon::parse('2026-10-12 08:00');
        $this->assertContains(Greeting::for('Nicolas Lefèvre', $monday), array_map(fn ($o) => str_replace('{prenom}', 'Nicolas', $o), Greeting::options($monday)));
        $this->assertTrue(collect(Greeting::options($monday))->every(fn ($o) => str_contains($o, 'lundi') || str_contains($o, 'semaine') || str_contains($o, 'reparti')));
        $this->assertTrue(collect(Greeting::options(Carbon::parse('2026-10-14 20:30')))->every(fn ($o) => ! str_starts_with($o, 'Bonjour')));
        $this->assertTrue(collect(Greeting::options(Carbon::parse('2026-10-17 10:00')))->contains(fn ($o) => str_contains($o, 'week-end') || str_contains($o, 'samedi')));

        // Stable pendant un créneau, varié sur la semaine.
        $this->assertSame(Greeting::for('Léa', Carbon::parse('2026-10-14 15:00'), 3), Greeting::for('Léa', Carbon::parse('2026-10-14 15:40'), 3));
        $week = collect(range(0, 6))->flatMap(fn ($d) => [9, 13, 16, 20])->map(fn ($h, $i) => Greeting::for('Léa', Carbon::parse('2026-10-12')->addDays(intdiv($i, 4))->setTime($h, 0), 3));
        $this->assertGreaterThan(6, $week->unique()->count());

        // Sans prénom : pas de virgule orpheline.
        foreach ([8, 12, 16, 20, 23] as $h) {
            $text = Greeting::for('', Carbon::parse('2026-10-12')->setTime($h, 0), 1);
            $this->assertDoesNotMatchRegularExpression('/(,\s*$|\s,|,\s*[?!])/u', $text);
        }
    }

    public function test_dashboard_shows_varied_greeting_with_first_name(): void
    {
        $user = $this->user(['name' => 'Camille Durand']);
        $this->travelTo(Carbon::parse('2026-10-16 17:00'));
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#data-greeting>[^<]*Camille[^<]*</h1>#u', $html);
        $this->assertStringContainsString(e(Greeting::for('Camille Durand', now(), $user->id)), $html);
    }

    public function test_horse_card_shows_care_due_only_within_30_days(): void
    {
        $user = $this->user();
        // Plusieurs chevaux : cartes des chevaux (un seul cheval a son propre tableau de bord).
        $this->grantLicense($user->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($user, null, ['usual_name' => 'Jack jack']);
        $this->horseFor($user, null, ['usual_name' => 'Uni']);
        $this->allHorses($user);
        $dentist = CareCategory::where('key', 'dentistry')->value('id');
        $care = CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $dentist, 'performed_at' => now()->subMonths(3), 'author_id' => $user->id, 'next_check_on' => now()->addMonths(9)->toDateString()]);
        $this->actingAs($user)->get('/dashboard')->assertDontSee('data-horse-due', false)->assertSee('À jour');

        $care->update(['next_check_on' => now()->addDays(25)->toDateString()]);
        $html = $this->actingAs($user)->get('/dashboard')->getContent();
        $this->assertMatchesRegularExpression('#data-horse-due class="[^"]*text-slate-500[^"]*">.*?Dentiste#s', $html);

        $care->update(['next_check_on' => now()->addDays(5)->toDateString()]);
        $this->assertMatchesRegularExpression('#data-horse-due class="[^"]*text-amber-700#', $this->actingAs($user)->get('/dashboard')->getContent());

        $care->update(['next_check_on' => now()->subDays(2)->toDateString()]);
        $this->actingAs($user)->get('/dashboard')->assertSee('en retard');
    }
}
