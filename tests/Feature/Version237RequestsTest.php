<?php

namespace Tests\Feature;

use App\Models\HorseAccessGrant;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Partages reçus : acceptés dans l'app, signalés dans le menu ; haut de page fixe (Partages, administration). */
class Version237RequestsTest extends TestCase
{
    private function invite(User $to, string $name, array $perms = [Perm::HORSE_VIEW, Perm::HEALTH_VIEW]): Invitation
    {
        $owner = $this->user();
        $horse = $this->horseFor($owner, null, ['official_name' => mb_strtoupper($name), 'usual_name' => $name]);

        return Invitation::create(['type' => 'horse_access', 'email' => strtolower($to->email), 'horse_id' => $horse->id, 'organization_id' => $horse->organization_id,
            'permissions' => $perms, 'invited_by' => $owner->id, 'token_hash' => hash('sha256', Str::random(40)), 'expires_at' => now()->addDays(14)]);
    }

    public function test_pending_shares_are_accepted_in_the_app_and_flagged_in_the_menu(): void
    {
        $me = $this->user();
        $a = $this->invite($me, 'Opale');
        $b = $this->invite($me, 'Galopin');
        $c = $this->invite($me, 'Jasmin');

        // Pastille dans le menu (entrée Partages et bouton Menu de la barre du bas).
        $this->actingAs($me)->get(route('dashboard'))->assertOk()
            ->assertSee('data-pending-shares>3<', false)->assertSee('data-pending-shares-bar', false);

        // Page Partages : boutons Accepter / Refuser, droits détaillés, plus de « lien reçu par email ».
        $this->actingAs($me)->get(route('sharing.index'))->assertOk()->assertSee('data-sharing-nav', false)->assertSee('jc-sticky-strip', false)
            ->assertSee('3 invitations en attente')->assertSee('Consulter les soins')->assertSee('data-accept-all', false)
            ->assertDontSee('Ouvrez le lien reçu par email');

        // Accepter : on reste sur la page Partages.
        $this->actingAs($me)->from(route('sharing.index'))->post(route('invitations.respond', $a), ['decision' => 'accept', 'stay' => 1])
            ->assertRedirect(route('sharing.index'))->assertSessionHas('success', 'Accès à Opale activé.');
        $this->assertTrue(HorseAccessGrant::where('user_id', $me->id)->where('horse_id', $a->horse_id)->exists());
        // Refuser.
        $this->actingAs($me)->from(route('sharing.index'))->post(route('invitations.respond', $b), ['decision' => 'decline'])->assertRedirect(route('sharing.index'));
        $this->assertNotNull($b->fresh()->revoked_at);
        // Tout accepter.
        $this->invite($me, 'Quartz');
        $this->actingAs($me)->from(route('sharing.index'))->post(route('invitations.accept-all'))->assertSessionHas('success', '2 invitations acceptées.');
        $this->assertSame(3, HorseAccessGrant::where('user_id', $me->id)->count());
        $this->actingAs($me)->get(route('dashboard'))->assertDontSee('data-pending-shares', false);

        // Une invitation adressée à quelqu'un d'autre ne peut pas être acceptée.
        $other = $this->invite($this->user(), 'Rubis');
        $this->actingAs($me)->post(route('invitations.respond', $other), ['decision' => 'accept'])->assertNotFound();
        $this->actingAs($me)->post(route('invitations.accept-all'));
        $this->assertNull($other->fresh()->accepted_at);
    }

    public function test_admin_navigation_sticks_under_the_header(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.dashboard'))->assertOk()
            ->assertSee('class="jc-sticky-strip -mx-4 mb-5 overflow-x-auto border-b', false);
    }

    public function test_simulated_shares_notify_in_the_app_and_are_purged(): void
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $as = fn () => $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $run = $as()->postJson(route('admin.simulation.store'), ['accounts' => 2, 'horses' => 1, 'events' => 0, 'sessions' => 0, 'cares' => 0, 'expenses' => 0, 'logs' => 0,
            'past_days' => 5, 'future_days' => 5, 'chunk' => 2, 'shares' => 2])->json();
        $as()->postJson(route('admin.simulation.step', $run['id']));
        $notes = $admin->notifications()->get();
        $this->assertCount(2, $notes);
        $this->assertSame(route('sharing.index'), $notes->first()->data['url']);
        $this->assertStringContainsString('Demi-pension', $notes->pluck('data.body')->implode(' '));

        $real = $admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'x', 'data' => ['kind' => 'announcement', 'title' => 'Vrai', 'body' => 'Message réel.', 'url' => null]]);
        $as()->postJson(route('admin.simulation.purge', $run['id']))->assertJson(['left' => 0]);
        $this->assertSame([$real->id], $admin->notifications()->pluck('id')->all());
    }
}
