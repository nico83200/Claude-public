<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\RidingSession;
use App\Models\UserProfile;
use App\Services\RidingSessionService;
use Tests\TestCase;

/** Exercices proposés au cavalier : ses habitudes (séances passées) et son niveau en galop. */
class ExerciseSuggestionsTest extends TestCase
{
    public function test_usual_exercises_and_galop_level_are_suggested(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $favorite = Exercise::where('scope', 'default')->where('level', 'advanced')->firstOrFail();
        $sessions = app(RidingSessionService::class);
        // Trois séances passées avec le même exercice en échauffement.
        foreach ([3, 10, 20] as $days) {
            $this->travelTo(now()->subDays($days + 2));
            $s = $sessions->create($horse, $user, ['scheduled_at' => now()->addDays(2), 'session_type' => 'dressage', 'planned_minutes' => 45],
                [['exercise_id' => $favorite->id, 'phase' => 'warmup']]);
            $this->travelBack();
            $s->forceFill(['status' => 'completed'])->save();
            $s->items()->update(['status' => 'done']);
        }
        $next = $sessions->create($horse, $user, ['scheduled_at' => now()->addDay(), 'session_type' => 'dressage', 'planned_minutes' => 45]);

        // Sans niveau : la fenêtre le demande ; les habitudes sont déjà proposées.
        $page = $this->actingAs($user)->get("/seances/{$next->id}/preparer")->assertOk()
            ->assertSee('data-ask-galop', false)->assertSee('data-suggestions="warmup"', false)->assertSee('Fait 3 fois');
        $this->assertStringContainsString($favorite->name, $this->section($page->getContent(), 'warmup'));
        // Suggestions déjà prêtes pour chaque niveau : affichées dès le choix du galop, sans recharger la page.
        $page->assertSee('data-variant="2"', false)->assertSee('data-variant="4"', false)->assertSee('data-variant="6"', false);
        $this->actingAs($user)->postJson('/parametres/niveau', ['galop' => 5])->assertOk()->assertJson(['galop' => 5]);

        // Le galop choisi est enregistré dans la fiche ; les étapes sans habitudes reçoivent des exercices de son niveau.
        $this->actingAs($user)->post('/parametres/niveau', ['galop' => 2])->assertSessionHas('success');
        $this->assertSame('Galop 2', $user->fresh()->profile->riding_level);
        $html = $this->actingAs($user)->get("/seances/{$next->id}/preparer")->assertDontSee('data-ask-galop', false)->assertSee('Adapté au niveau débutant (galops 1 à 3)')->getContent();
        $cooldown = $this->section($html, 'cooldown');
        $this->assertNotSame('', $cooldown);
        foreach (Exercise::where('level', 'advanced')->pluck('name') as $hard) {
            $this->assertStringNotContainsString('>'.e($hard).'<', $cooldown, 'Pas d\'exercice confirmé proposé à un galop 2.');
        }
        $this->actingAs($user)->post('/parametres/niveau', ['galop' => 9])->assertSessionHasErrors('galop');
    }

    public function test_galop_is_read_from_profile_text(): void
    {
        $this->assertSame([4, 6, null, 1], [UserProfile::parseGalop('galop 4'), UserProfile::parseGalop('G6'), UserProfile::parseGalop('débutant'), UserProfile::parseGalop('1')]);
        $this->assertSame(['beginner', 'intermediate', 'intermediate', 'advanced'], [UserProfile::exerciseLevel(3), UserProfile::exerciseLevel(4), UserProfile::exerciseLevel(5), UserProfile::exerciseLevel(7)]);
        $user = $this->user();
        $this->actingAs($user)->put('/parametres/profil', ['name' => $user->name, 'email' => $user->email, 'riding_level' => 'g5'])->assertSessionHasNoErrors();
        $this->assertSame('Galop 5', $user->fresh()->profile->riding_level);
        $this->actingAs($user)->get('/parametres')->assertSee('data-riding-level', false);
    }

    private function section(string $html, string $phase): string
    {
        return preg_match('#data-suggestions="'.$phase.'" data-variant="">(.*?)</section>#s', $html, $m) ? $m[1] : '';
    }

    public function test_exercises_page_suggests_by_galop_and_habits(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        // Sans galop : question d'abord, suggestions de chaque niveau prêtes pour l'affichage immédiat.
        $this->actingAs($user)->get('/exercices')->assertOk()->assertSee('data-for-you', false)->assertSee('data-ask-galop', false)
            ->assertSee('data-variant="2"', false)->assertSee('data-variant="6"', false);
        // Habitude prise en séance : proposée en tête, avec son nombre de passages.
        $fav = Exercise::where('scope', 'default')->firstOrFail();
        $this->travelTo(now()->subDays(5));
        $s = app(RidingSessionService::class)->create($horse, $user, ['scheduled_at' => now()->addDay(), 'session_type' => 'free', 'planned_minutes' => 30], [['exercise_id' => $fav->id, 'phase' => 'main']]);
        $this->travelBack();
        $s->forceFill(['status' => 'completed'])->save();
        $this->actingAs($user)->post('/parametres/niveau', ['galop' => 6])->assertSessionHasNoErrors();
        $user->refresh();
        $html = $this->actingAs($user)->get('/exercices')->assertOk()->assertDontSee('data-ask-galop', false)->assertSee('Adapté au niveau expert (galop 6 et plus)')->getContent();
        $this->assertMatchesRegularExpression('#data-for-you-phase="main" data-variant="">.*?'.preg_quote(e($fav->name), '#').'.*?Fait 1 fois#s', $html);
        // Recherche ou filtre : liste habituelle sans l'encart.
        $this->actingAs($user)->get('/exercices?q=volte')->assertOk()->assertDontSee('data-for-you', false);
    }
}
