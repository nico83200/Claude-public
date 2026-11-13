<?php

namespace Tests\Feature;

use App\Models\HalfLease;
use App\Models\HorseAccessGrant;
use App\Models\Invitation;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Tests\TestCase;

/** Partages de chevaux ouverts d'office (plus d'acceptation), sauf demi-pension ; la personne peut toujours quitter. */
class Version253RequestsTest extends TestCase
{
    private function owner(): array
    {
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');

        return [$owner, $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni'])];
    }

    private function titles($user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }

    public function test_share_with_an_existing_account_opens_at_once(): void
    {
        [$owner, $horse] = $this->owner();
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $this->actingAs($owner)->post(route('horses.sharing.invite', $horse), ['email' => 'Lea@Exemple.fr', 'label' => 'Cavalière', 'permissions' => [Perm::SESSIONS_VIEW]])
            ->assertSessionHas('success', 'Partage ouvert : Léa DURAND a maintenant accès à Uni et en est prévenu.');
        $grant = HorseAccessGrant::sole();
        $this->assertSame([$lea->id, 'Cavalière'], [$grant->user_id, $grant->label]);
        $this->assertNotNull(Invitation::sole()->accepted_at);
        $this->assertContains('Paul MARTIN partage Uni avec vous', $this->titles($lea));
        $this->assertNotContains('Invitation acceptée', $this->titles($owner), 'Le propriétaire a fait le geste lui-même.');
        app()->forgetInstance(HorseAccess::class);
        $this->actingAs($lea)->get(route('horses.show', $horse))->assertOk();
        $this->actingAs($lea)->get(route('sharing.index'))->assertOk()->assertDontSee('Tout accepter');

        // Elle peut toujours quitter le partage.
        $this->actingAs($lea)->post(route('sharing.received.leave', $grant))->assertRedirect();
        $this->assertFalse(HorseAccessGrant::current()->where('user_id', $lea->id)->exists());
    }

    public function test_share_by_email_opens_once_the_address_is_verified(): void
    {
        [$owner, $horse] = $this->owner();
        $this->actingAs($owner)->post(route('horses.sharing.invite', $horse), ['email' => 'nouvelle@exemple.fr', 'permissions' => []])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'dès que la personne aura créé son compte'));
        $this->assertSame(0, HorseAccessGrant::count());
        $this->actingAs($owner)->get(route('horses.sharing', $horse))->assertSee('En attente d\'inscription', false);

        // Compte créé mais adresse pas encore vérifiée : rien n'est ouvert.
        $nina = $this->user(['email' => 'nouvelle@exemple.fr', 'email_verified_at' => null]);
        $this->actingAs($nina)->get(route('dashboard'));
        event(new Login('web', $nina, false));
        $this->assertSame(0, HorseAccessGrant::count());
        // Adresse vérifiée : le partage s'ouvre de lui-même.
        $nina->markEmailAsVerified();
        event(new Verified($nina));
        $this->assertSame($nina->id, HorseAccessGrant::sole()->user_id);
        $this->assertContains('Paul MARTIN partage Uni avec vous', $this->titles($nina));
    }

    public function test_pending_shares_open_at_login_but_a_half_lease_still_waits_for_agreement(): void
    {
        [$owner, $horse] = $this->owner();
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        // Proposition de demi-pension : toujours à accepter.
        $this->actingAs($owner)->post(route('horses.half-leases.store', $horse), [
            'name' => 'Léa DURAND', 'email' => 'lea@exemple.fr', 'starts_on' => now()->toDateString(), 'weekdays' => [2], 'monthly_amount' => '0', 'permissions' => Perm::halfLeasePreset(),
        ])->assertSessionHasNoErrors();
        event(new Login('web', $lea, false));
        $this->assertSame('proposed', HalfLease::sole()->status);
        $this->assertSame(0, HorseAccessGrant::count());
        $this->actingAs($lea)->get(route('dashboard'))->assertSee('data-half-lease-invitation', false);

        // Partage simple resté en attente (envoyé avant la mise à jour) : ouvert à la connexion suivante.
        $other = $this->horseFor($this->user(), null, ['official_name' => 'CAPUCINE']);
        $pending = Invitation::create(['type' => 'horse_access', 'email' => 'lea@exemple.fr', 'horse_id' => $other->id, 'organization_id' => $other->organization_id,
            'permissions' => [Perm::HORSE_VIEW], 'label' => 'Partage', 'invited_by' => $other->organization->users()->first()->id, 'token_hash' => hash('sha256', 'x'), 'expires_at' => now()->addDays(14)]);
        event(new Login('web', $lea, false));
        $this->assertNotNull($pending->fresh()->accepted_at);
        $this->assertSame([$other->id], HorseAccessGrant::pluck('horse_id')->all());
        $this->assertSame('proposed', HalfLease::sole()->status, 'La demi-pension attend toujours son accord.');
    }
}
