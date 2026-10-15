<?php

namespace Tests\Feature;

use App\Models\HorseAccessGrant;
use App\Notifications\AppNotification;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** La personne à qui un cheval est partagé peut suspendre, reprendre ou quitter ce partage. */
class ReceivedShareControlTest extends TestCase
{
    public function test_recipient_pauses_resumes_and_leaves_a_share(): void
    {
        Notification::fake();
        $owner = $this->user(['name' => 'Claire Dupont']);
        $this->grantLicense($owner->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNI DU BOIS', 'usual_name' => "Uni d'Or"]);
        $rider = $this->user(['name' => 'Léa Martin']);
        $grant = HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view'], 'granted_by' => $owner->id]);

        $this->actingAs($rider)->get('/partages')->assertOk()->assertSee('data-received-grant', false)->assertSee('Suspendre')->assertSee('Quitter');
        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertOk();

        // Suspension : plus d'accès au dossier, ni dans la liste des chevaux ; le propriétaire est prévenu.
        $this->actingAs($rider)->post("/partages/recus/{$grant->id}/suspendre")->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($grant->fresh()->paused_at);
        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertForbidden();
        $this->actingAs($rider)->get('/chevaux')->assertOk()->assertDontSee("Uni d'Or");
        $this->actingAs($rider)->get('/partages')->assertSee('Suspendu depuis le')->assertSee('Reprendre');
        $this->actingAs($owner)->get("/chevaux/{$horse->id}/partage")->assertSee('Suspendu par la personne invitée');
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => $n->kind === 'access_changed' && str_contains($n->body, 'Léa Martin a suspendu'));

        // Reprise : mêmes droits qu'avant.
        $this->actingAs($rider)->post("/partages/recus/{$grant->id}/reprendre")->assertRedirect();
        $this->assertNull($grant->fresh()->paused_at);
        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertOk();
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => str_contains($n->body, 'a repris son accès'));

        // Personne d'autre ne peut agir sur ce partage (ni le propriétaire par ces adresses).
        $this->actingAs($owner)->post("/partages/recus/{$grant->id}/suspendre")->assertNotFound();
        $this->actingAs($this->user())->post("/partages/recus/{$grant->id}/quitter")->assertNotFound();

        // Quitter : définitif, le propriétaire est prévenu (e-mail compris).
        $this->actingAs($rider)->post("/partages/recus/{$grant->id}/quitter")->assertRedirect('/partages');
        $grant->refresh();
        $this->assertNotNull($grant->revoked_at);
        $this->assertSame($rider->id, $grant->revoked_by);
        $this->actingAs($rider)->get("/chevaux/{$horse->id}")->assertForbidden();
        $this->actingAs($rider)->get('/partages')->assertDontSee('data-received-grant', false);
        $this->actingAs($rider)->post("/partages/recus/{$grant->id}/reprendre")->assertStatus(422);
        $this->actingAs($owner)->get("/chevaux/{$horse->id}/partage")->assertSee('quitté par la personne invitée');
        Notification::assertSentTo($owner, AppNotification::class, fn ($n) => $n->mail && str_contains($n->body, 'a mis fin à son accès'));
    }

    public function test_a_paused_share_still_counts_in_the_plan_share_limit(): void
    {
        $owner = $this->user();
        $this->grantLicense($owner->personalOrganization(), 'multi-chevaux');
        $horse = $this->horseFor($owner);
        $rider = $this->user();
        HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $rider->id, 'permissions' => ['horse.view'], 'paused_at' => now()]);
        $this->assertSame(1, app(InvitationService::class)->horseSharesUsed($owner->personalOrganization()));
    }
}
