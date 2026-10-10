<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\ExpenseCategory;
use App\Models\HorseAccessGrant;
use App\Models\HorseOrganizationAssignment;
use App\Models\Invitation;
use App\Models\Professional;
use App\Models\RidingSession;
use App\Models\Role;
use App\Notifications\AppNotification;
use App\Services\InvitationService;
use App\Support\Perm;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FeaturesTest extends TestCase
{
    public function test_every_main_page_renders_for_an_owner(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization());
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->format('Y-m-d H:i'), 'session_type' => 'free']);
        $session = RidingSession::first();
        $pages = ['/dashboard', '/chevaux', '/chevaux/nouveau', "/chevaux/{$horse->id}", "/chevaux/{$horse->id}/modifier", "/chevaux/{$horse->id}/sante", "/chevaux/{$horse->id}/soins/nouveau",
            "/chevaux/{$horse->id}/genealogie", "/chevaux/{$horse->id}/documents", "/chevaux/{$horse->id}/alimentation", "/chevaux/{$horse->id}/journal", "/chevaux/{$horse->id}/partage",
            "/chevaux/{$horse->id}/historique", "/chevaux/{$horse->id}/intervenants", '/chevaux/rechercher', '/sante', '/intervenants', '/intervenants/nouveau',
            '/calendrier', '/calendrier?view=week', '/calendrier?view=day', '/seances', '/seances/nouvelle', "/seances/{$session->id}", "/seances/{$session->id}/preparer",
            "/seances/{$session->id}/en-cours", "/seances/{$session->id}/bilan", '/modeles', '/exercices', '/exercices/nouveau', '/budget', '/partages', '/organisation', '/espaces/nouveau',
            '/notifications', '/abonnement', '/parametres', '/parametres/securite', '/parametres/donnees', '/parametres/appareils', '/recherche?q=to', '/hors-ligne'];
        foreach ($pages as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_horse_sharing_invitation_flow(): void
    {
        Notification::fake();
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->post("/chevaux/{$horse->id}/partage/invitations", ['email' => 'cavaliere@example.com', 'label' => 'Demi-pension', 'permissions' => Perm::halfLeasePreset(), 'expires_at' => now()->addMonths(6)->toDateString()])->assertSessionHas('success');
        $invitation = Invitation::firstOrFail();
        $this->assertNotSame('', $invitation->token_hash);

        // Le jeton en clair n'est transmis que par email : on le recrée via le service pour le test.
        [, $token] = app(InvitationService::class)->inviteToHorse($horse, $owner, 'cavaliere@example.com', Perm::halfLeasePreset(), null, null, null);
        $this->get("/invitations/{$token}")->assertOk()->assertSee($horse->shortName());

        $intruder = $this->user();
        $this->actingAs($intruder)->post("/invitations/{$token}/accepter")->assertSessionHasErrors('invitation');

        $rider = $this->user(['email' => 'cavaliere@example.com']);
        $this->actingAs($rider)->post("/invitations/{$token}/accepter")->assertRedirect("/chevaux/{$horse->id}");
        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertOk();
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => $n->kind === 'invitation_accepted');
        // Jeton à usage unique
        $this->actingAs($rider)->post("/invitations/{$token}/accepter")->assertSessionHasErrors('invitation');
    }

    public function test_free_plan_invitation_limit(): void
    {
        $owner = $this->user(); // Découverte : 1 invitation
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->post("/chevaux/{$horse->id}/partage/invitations", ['email' => 'a@example.com', 'permissions' => ['horse.view']])->assertSessionHas('success');
        $this->actingAs($owner)->post("/chevaux/{$horse->id}/partage/invitations", ['email' => 'b@example.com', 'permissions' => ['horse.view']])->assertSessionHasErrors('email');
    }

    public function test_stable_membership_invitation_and_boarding_assignment(): void
    {
        Notification::fake();
        $manager = $this->user();
        $stable = $this->stable($manager);
        $manager->forceFill(['current_organization_id' => $stable->id])->save();
        [, $token] = app(InvitationService::class)->inviteToOrganization($stable, $manager, 'salarie@example.com', Role::system('staff'));
        $staff = $this->user(['email' => 'salarie@example.com']);
        $this->actingAs($staff)->post("/invitations/{$token}/accepter")->assertRedirect('/dashboard');
        $this->assertTrue($staff->fresh()->memberships()->where('organization_id', $stable->id)->exists());

        // Un propriétaire confie son cheval à l'écurie
        $owner = $this->user();
        $horse = $this->horseFor($owner);
        $this->actingAs($owner)->post("/chevaux/{$horse->id}/partage/ecurie", ['stable_code' => $stable->slug, 'kind' => 'boarding', 'permissions' => [Perm::FEEDING_VIEW, Perm::DAILYLOG_CREATE]])->assertSessionHas('success');
        $assignment = HorseOrganizationAssignment::firstOrFail();
        $this->actingAs($staff)->get("/chevaux/{$horse->id}")->assertForbidden(); // en attente
        $this->actingAs($manager)->post("/organisation/rattachements/{$assignment->id}/accepter")->assertRedirect();
        $this->actingAs($staff)->get("/chevaux/{$horse->id}")->assertOk();
        $this->actingAs($staff)->post("/chevaux/{$horse->id}/journal", ['appetite' => 'good', 'observations' => 'Mangé'])->assertRedirect();
        $this->actingAs($staff)->get("/chevaux/{$horse->id}/sante")->assertForbidden();
        $this->assertDatabaseHas('horse_locations', ['horse_id' => $horse->id, 'organization_id' => $stable->id]);
    }

    public function test_calendar_events_and_recurrence(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/calendrier', ['horse_id' => $horse->id, 'type' => 'farrier', 'title' => 'Ferrure', 'date' => now()->startOfMonth()->addDays(2)->toDateString(), 'time' => '10:00', 'recurrence' => 'weekly', 'recurrence_until' => now()->startOfMonth()->addDays(20)->toDateString()])->assertRedirect();
        $this->assertSame(1, CalendarEvent::count()); // une seule ligne, occurrences calculées
        $res = $this->actingAs($user)->get('/calendrier?view=month&date='.now()->toDateString())->assertOk()->assertSee('Ferrure');
        $this->assertSame(3, $res->viewData('occurrences')->count());
        $other = $this->user();
        $this->actingAs($other)->get('/calendrier/'.CalendarEvent::first()->id)->assertForbidden();
    }

    public function test_budget_expenses_kpis_and_exports(): void
    {
        $user = $this->user();
        $this->grantLicense($user->personalOrganization(), 'particulier');
        $horse = $this->horseFor($user);
        $cat = ExpenseCategory::where('key', 'feed')->first();
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'spent_on' => now()->toDateString(), 'amount' => '120.50', 'supplier' => 'Sellerie'])->assertSessionHas('success');
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => $cat->id, 'spent_on' => now()->toDateString(), 'amount' => '10.255'])->assertSessionHasErrors('amount');
        $res = $this->actingAs($user)->get('/budget')->assertOk();
        $this->assertEquals(120.50, (float) $res->viewData('kpis')['month']);
        $csv = $this->actingAs($user)->get('/budget/export.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('120,50', $csv);
        $this->actingAs($user)->get('/budget/export.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->user())->get('/budget/export.csv')->assertOk()->assertDontSee('Sellerie');
    }

    public function test_free_plan_without_budget_feature_cannot_add_expenses(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/budget', ['horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::first()->id, 'spent_on' => now()->toDateString(), 'amount' => '10'])->assertSessionHas('warning');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_session_exports(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post('/seances', ['horse_id' => $horse->id, 'scheduled_at' => now()->format('Y-m-d H:i'), 'session_type' => 'jumping', 'objective' => 'Gymnastique']);
        $this->assertStringContainsString('Gymnastique', $this->actingAs($user)->get("/chevaux/{$horse->id}/export/seances.csv")->assertOk()->streamedContent());
        $this->actingAs($user)->get("/chevaux/{$horse->id}/export/seances.pdf")->assertOk();
    }

    public function test_personal_data_export_requires_password_confirmation(): void
    {
        $user = $this->user();
        $this->horseFor($user, null, ['official_name' => 'MON CHEVAL']);
        $this->actingAs($user)->get('/parametres/donnees/export')->assertRedirect('/user/confirm-password');
        $json = $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/parametres/donnees/export')->assertOk()->streamedContent();
        $data = json_decode($json, true);
        $this->assertSame($user->email, $data['account']['email']);
        $this->assertSame('MON CHEVAL', $data['horses_owned_space'][0]['official_name']);
    }

    public function test_reminders_are_sent_once_and_only_to_authorized_people(): void
    {
        Notification::fake();
        $owner = $this->user();
        $viewer = $this->user();
        $horse = $this->horseFor($owner);
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $viewer->id, 'permissions' => ['horse.view']]);
        $event = new CalendarEvent(['horse_id' => $horse->id, 'type' => 'veterinary', 'title' => 'Vaccin', 'starts_at' => now()->addDay()->setTime(10, 0)]);
        $event->organization_id = $horse->organization_id;
        $event->save();

        $this->artisan('reminders:send')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();
        Notification::assertSentToTimes($owner, AppNotification::class, 1);
        Notification::assertNotSentTo($viewer, AppNotification::class);
    }

    public function test_notifications_page_and_internal_redirect_only(): void
    {
        $user = $this->user();
        $user->notify(new AppNotification('observation', 'Titre', 'Corps', 'https://malveillant.example/phishing', false));
        $id = $user->notifications()->first()->id;
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Titre');
        $this->actingAs($user)->get("/notifications/{$id}")->assertRedirect('/notifications');
        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_feeding_plan_history_is_preserved(): void
    {
        $user = $this->user();
        $horse = $this->horseFor($user);
        $this->actingAs($user)->post("/chevaux/{$horse->id}/alimentation", ['name' => 'Hiver', 'starts_on' => now()->subMonth()->toDateString(), 'entries' => [['feed' => 'Foin', 'quantity' => 8]]])->assertRedirect();
        $this->actingAs($user)->post("/chevaux/{$horse->id}/alimentation", ['name' => 'Printemps', 'starts_on' => now()->toDateString(), 'entries' => [['feed' => 'Herbe']]])->assertRedirect();
        $this->assertSame(2, $horse->feedingPlans()->count());
        $this->assertNotNull($horse->feedingPlans()->where('name', 'Hiver')->first()->ends_on);
        $this->actingAs($user)->get("/chevaux/{$horse->id}/alimentation")->assertOk()->assertSee('Printemps')->assertSee('Hiver');
    }

    public function test_professional_directory_and_duplicate_detection(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/intervenants', ['last_name' => 'Martin', 'kind' => 'farrier', 'phone' => '0600000000'])->assertRedirect();
        $this->actingAs($user)->post('/intervenants', ['last_name' => 'Martin', 'kind' => 'farrier', 'phone' => '0600000000'])->assertSessionHas('warning');
        $this->assertSame(1, Professional::count());
        $this->actingAs($user)->post('/intervenants', ['last_name' => 'Martin', 'kind' => 'farrier', 'phone' => '0600000000', 'force' => '1']);
        $this->assertSame(2, Professional::count());
        $this->actingAs($this->user())->get('/intervenants/'.Professional::first()->id)->assertNotFound();
    }
}
