<?php

namespace Tests\Feature;

use App\Models\AppUpdate;
use App\Models\CalendarEvent;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HorseAccessGrant;
use App\Models\Professional;
use App\Services\OrganizationService;
use App\Services\RidingSessionService;
use Tests\TestCase;

/** Demandes du 15/10 pour la version 2.20.0 : menu, historique des versions, santé, exercices, espaces, intervenants, budget, chevaux, agenda. */
class NextVersionRequestsTest extends TestCase
{
    public function test_version_is_shown_in_the_menu_to_super_admins_only(): void
    {
        $version = trim(file_get_contents(base_path('VERSION')));
        $user = $this->user();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('data-app-version', false);

        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Version '.$version)->getContent();
        // Au-dessus du lien Instagram.
        $this->assertLessThan(strpos($html, 'data-instagram-link'), strpos($html, 'data-app-version'));
    }

    public function test_update_page_lists_versions_with_install_date_and_summary(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        AppUpdate::create(['user_id' => $admin->id, 'from_version' => '2.17.0', 'to_version' => '2.19.0', 'status' => 'done', 'finished_at' => '2026-10-15 18:30:00']);

        $page = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/mises-a-jour')->assertOk()->assertSee('Historique des versions')
            ->assertSee('data-version="2.19.0"', false)->assertSee('« C\'est fait » et « Reporter d\'une semaine »');
        $html = $page->getContent();
        $block = fn (string $v) => substr($html, strpos($html, 'data-version="'.$v.'"'), 900);
        $this->assertStringContainsString('Installée le 15/10/2026 à 18:30', $block('2.19.0'));
        // Version sautée : installée avec la 2.19.0.
        $this->assertStringContainsString('avec la 2.19.0', $block('2.18.0'));
        // Antérieure à la première mise à jour : installation initiale.
        $this->assertStringContainsString('Installation initiale', $block('2.16.0'));
        // Chaque version publiée a son résumé.
        $current = trim(file_get_contents(base_path('VERSION')));
        $this->assertArrayHasKey($current, require resource_path('data/versions.php'), 'Ajouter la version courante dans resources/data/versions.php');
    }

    public function test_vet_share_is_offered_from_the_health_page_with_a_horse_choice(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'multi-chevaux');
        $jack = $this->horseFor($owner, null, ['official_name' => 'JACK']);
        $this->actingAs($owner)->get('/sante')->assertOk()
            ->assertSee('href="'.route('horses.vet-summary', $jack).'" class="btn-secondary" data-vet-share', false)->assertDontSee('data-vet-share-picker', false);

        $uni = $this->horseFor($owner, null, ['official_name' => 'UNI']);
        $this->actingAs($owner)->get('/sante')->assertOk()->assertSee('data-vet-share-picker', false)
            ->assertSee(route('horses.vet-summary', $jack))->assertSee(route('horses.vet-summary', $uni));
    }

    public function test_exercise_suggestions_need_an_offer_with_the_ai_assistant(): void
    {
        $free = $this->user();
        $horse = $this->horseFor($free);
        $session = app(RidingSessionService::class)->create($horse, $free, ['scheduled_at' => now()->addDay(), 'session_type' => 'dressage', 'planned_minutes' => 45]);

        // Offre gratuite : pas de suggestions, une accroche vers l'offre avec l'assistant IA.
        $this->actingAs($free)->get('/exercices')->assertOk()->assertSee('data-suggestions-locked', false)
            ->assertSee('Des exercices choisis pour vous, à chaque séance')->assertDontSee('data-for-you', false);
        $this->actingAs($free)->get("/seances/{$session->id}/preparer")->assertOk()->assertSee('data-suggestions-locked', false)
            ->assertDontSee('data-ask-galop', false)->assertDontSee('data-suggestions="warmup"', false);

        // Offre avec l'assistant : suggestions affichées, plus d'accroche.
        $this->grantLicense($free->personalOrganization(), 'particulier');
        $this->actingAs($free)->get('/exercices')->assertOk()->assertDontSee('data-suggestions-locked', false);
        $this->actingAs($free)->get("/seances/{$session->id}/preparer")->assertOk()->assertDontSee('data-suggestions-locked', false)->assertSee('data-ask-galop', false);
    }

    public function test_personal_space_carries_the_rider_name_and_follows_renames(): void
    {
        $user = $this->user(['name' => 'Nicolas LEFEVRE']);
        $this->assertSame('Nicolas LEFEVRE', $user->personalOrganization()->name);

        $this->actingAs($user)->put('/parametres/profil', ['name' => 'Nicolas Lefèvre', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertSame('Nicolas Lefèvre', $user->personalOrganization()->name);

        // Ancien libellé « Espace de … » : remplacé au prochain changement de nom.
        $user->personalOrganization()->update(['name' => 'Espace de Nicolas Lefèvre']);
        $user->fresh()->update(['name' => 'Nico Lefèvre']);
        $this->assertSame('Nico Lefèvre', $user->personalOrganization()->name);
        // Un espace renommé autrement par son propriétaire est conservé.
        $user->personalOrganization()->update(['name' => 'Les chevaux de Nico']);
        $user->fresh()->update(['name' => 'N. Lefèvre']);
        $this->assertSame('Les chevaux de Nico', $user->personalOrganization()->name);
        app(OrganizationService::class)->syncPersonalName($user->fresh(), null);
        $this->assertSame('Les chevaux de Nico', $user->personalOrganization()->name);
    }

    public function test_professional_cards_have_a_direct_edit_button_for_managers(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/intervenants', ['last_name' => 'Martin', 'kind' => 'farrier', 'phone' => '0600000000'])->assertRedirect();
        $pro = Professional::sole();
        $this->actingAs($user)->get('/intervenants')->assertOk()
            ->assertSee('href="'.route('professionals.edit', $pro).'"', false)->assertSee('data-edit-professional="'.$pro->id.'"', false);
    }

    public function test_budget_shows_ten_expenses_and_loads_ten_more(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner);
        $category = ExpenseCategory::value('id');
        foreach (range(1, 23) as $i) {
            $e = new Expense(['horse_id' => $horse->id, 'expense_category_id' => $category, 'spent_on' => now()->subDays($i)->toDateString(), 'amount' => $i, 'comment' => 'Dépense n°'.$i]);
            $e->organization_id = $horse->organization_id;
            $e->author_id = $owner->id;
            $e->paid_on = $e->spent_on; // réglées : hors de la liste « À payer »
            $e->save();
        }
        $ids = Expense::orderByDesc('spent_on')->pluck('id');

        $page = $this->actingAs($owner)->get('/budget')->assertOk()->assertSee('data-load-more', false)->assertSee('Afficher 10 dépenses de plus');
        $this->assertSame(10, substr_count($page->getContent(), 'data-swipe-row'));
        $page->assertSee('data-expense-link="'.$ids[9].'"', false)->assertDontSee('data-expense-link="'.$ids[10].'"', false);

        $more = $this->actingAs($owner)->getJson('/budget/depenses?page=2')->assertOk();
        $this->assertSame(10, substr_count($more->json('mobile'), 'data-swipe-row'));
        $this->assertStringContainsString('data-expense-link="'.$ids[10].'"', $more->json('desktop'));
        $this->assertStringContainsString('page=3', $more->json('next'));
        $last = $this->actingAs($owner)->getJson('/budget/depenses?page=3')->assertOk();
        $this->assertSame(3, substr_count($last->json('mobile'), 'data-swipe-row'));
        $this->assertNull($last->json('next'));

        // Personne sans accès au budget de ce cheval : rien.
        $this->assertSame('', trim($this->actingAs($this->user())->getJson('/budget/depenses?page=1')->assertOk()->json('mobile')));
    }

    public function test_a_single_horse_gets_a_large_card_and_the_menu_says_horse(): void
    {
        $owner = $this->user();
        $jack = $this->horseFor($owner, null, ['official_name' => 'JACK']);
        $this->actingAs($owner)->get('/chevaux')->assertOk()->assertSee('data-single-horse', false)->assertSee('Ouvrir le dossier')->assertDontSee('dossier(s)');
        $html = $this->actingAs($owner)->get('/dashboard')->getContent();
        $this->assertStringContainsString('Cheval</a>', $html);
        $this->assertStringNotContainsString('Chevaux</a>', $html);

        // Un cheval partagé avec la personne : liste habituelle, menu « Chevaux ».
        $other = $this->user();
        $shared = $this->horseFor($other);
        HorseAccessGrant::create(['horse_id' => $shared->id, 'user_id' => $owner->id, 'permissions' => ['horse.view']]);
        $this->actingAs($owner)->get('/chevaux')->assertOk()->assertDontSee('data-single-horse', false)->assertSee('2 dossier(s)');
        $this->assertStringContainsString('Chevaux</a>', $this->actingAs($owner)->get('/dashboard')->getContent());

        // Seul cheval partagé (aucun à soi) : pas de grande carte, mais le menu dit « Cheval ».
        $solo = $this->user();
        HorseAccessGrant::create(['horse_id' => $jack->id, 'user_id' => $solo->id, 'permissions' => ['horse.view']]);
        $this->actingAs($solo)->get('/chevaux')->assertOk()->assertDontSee('data-single-horse', false);
    }

    public function test_choosing_session_or_lesson_in_a_new_event_opens_the_session_form(): void
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->get('/calendrier')->assertOk()->assertSee('data-session-redirect', false)
            ->assertSee('seances\\/nouvelle', false)->assertSee('la préparation de séance s\'ouvre');
        // Le formulaire de séance reprend cheval, date, heure et type.
        $this->actingAs($owner)->get('/seances/nouvelle?horse='.$horse->id.'&date='.urlencode('2026-11-02 14:30').'&type=private_lesson')->assertOk()
            ->assertSee('value="2026-11-02T14:30"', false)->assertSee('<option value="private_lesson" selected', false);
        // Modification d'un événement existant : pas de redirection.
        $this->actingAs($owner)->post('/calendrier', ['horse_id' => $horse->id, 'type' => 'custom', 'title' => 'Visite', 'date' => now()->addDay()->toDateString(), 'time' => '10:00', 'duration_minutes' => 30]);
        $event = CalendarEvent::where('title', 'Visite')->sole();
        $this->actingAs($owner)->get(route('calendar.edit', $event))->assertOk()->assertDontSee('data-session-redirect', false);
    }
}
