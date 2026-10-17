<?php

namespace Tests\Feature;

use App\Models\HalfLease;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\Invitation;
use App\Models\RidingDay;
use App\Models\User;
use App\Services\HorseAccess;
use App\Services\InvitationService;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Fiche demi-pension : proposition, acceptation (accès « Demi-pension »), jours habituels, essai, fin. */
class Version246RequestsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setUpOwner(): array
    {
        Carbon::setTestNow('2026-10-17 18:00');
        $owner = $this->user(['name' => 'Paul MARTIN']);
        $this->grantLicense($owner->personalOrganization(), 'particulier');
        $horse = $this->horseFor($owner, null, ['official_name' => 'UNIVERS', 'usual_name' => 'Uni']);

        return [$owner, $horse];
    }

    private function propose(User $owner, Horse $horse, array $data = [])
    {
        return $this->actingAs($owner)->post(route('horses.half-leases.store', $horse), array_merge([
            'name' => 'Léa DURAND', 'email' => 'lea@exemple.fr', 'phone' => '06 12 34 56 78', 'starts_on' => '2026-11-01', 'trial_ends_on' => '2026-11-30',
            'notice_days' => 30, 'weekdays' => [2, 4], 'monthly_amount' => '180', 'payment_day' => 5, 'notes' => 'Balades le week-end avec accord.',
            'permissions' => Perm::halfLeasePreset(),
        ], $data));
    }

    private function fresh(): void
    {
        app()->forgetInstance(HorseAccess::class);
    }

    private function titles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }

    public function test_proposal_to_a_new_person_then_acceptance_after_signing_up(): void
    {
        [$owner, $horse] = $this->setUpOwner();
        $this->actingAs($owner)->get(route('horses.sharing', $horse))->assertOk()->assertSee('data-half-leases', false)->assertSee(route('horses.half-leases.create', $horse), false);
        $this->actingAs($owner)->get(route('horses.half-leases.create', $horse))->assertOk()->assertSee('data-half-lease-form', false);
        $this->propose($owner, $horse)->assertSessionHas('invite_link');
        $lease = HalfLease::sole();
        $invitation = Invitation::sole();
        $this->assertSame(['proposed', 'Demi-pension', 'lea@exemple.fr'], [$lease->status, $invitation->label, $invitation->email]);
        $this->assertSame($invitation->id, $lease->invitation_id);
        $this->assertSame(0, HorseAccessGrant::count(), 'Pas d\'accès avant son accord.');
        $this->actingAs($owner)->get(route('half-leases.show', $lease))->assertOk()->assertSee('En attente de la réponse de Léa DURAND')
            ->assertSee('180,00 € par mois, payable le 5')->assertSee('Mardi et jeudi')->assertSee('data-invite-link', false);

        // Inscription avec l'adresse invitée : la page d'invitation montre les conditions, puis acceptation.
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $token = app(InvitationService::class)->resend($invitation, $owner);
        $this->actingAs($lea)->get(route('invitations.show', $token))->assertOk()->assertSee('Proposition de demi-pension')->assertSee('mardi et jeudi')->assertSee('Préavis : 1 mois');
        $this->actingAs($lea)->post(route('invitations.accept', $token))->assertRedirect(route('half-leases.show', $lease));
        $lease->refresh();
        $grant = HorseAccessGrant::sole();
        $this->assertSame(['active', $lea->id, $grant->id], [$lease->status, $lease->user_id, $lease->grant_id]);
        $this->assertSame(['Demi-pension', $lea->id], [$grant->label, $grant->user_id]);
        $this->assertEqualsCanonicalizing(Perm::halfLeasePreset(), $grant->permissions);
        $this->assertContains('Demi-pension acceptée : Uni', $this->titles($owner));
        $this->assertSame(0, RidingDay::count(), 'Jours habituels à partir du 1er novembre seulement.');

        // Le 1er novembre, la tâche quotidienne inscrit ses jours dans la semaine type.
        Carbon::setTestNow('2026-11-01 07:00');
        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame([2 => $lea->id, 4 => $lea->id], RidingDay::where('horse_id', $horse->id)->pluck('user_id', 'weekday')->all());
        $this->fresh();
        $this->actingAs($lea)->get(route('half-leases.show', $lease))->assertOk()->assertSee('data-half-lease-end', false)->assertDontSee('data-half-lease-accept', false);
    }

    public function test_existing_account_sees_the_proposal_in_the_app_and_can_decline(): void
    {
        [$owner, $horse] = $this->setUpOwner();
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $this->propose($owner, $horse);
        $lease = HalfLease::sole();
        $this->assertContains('Proposition de demi-pension : Uni', $this->titles($lea));
        // Accueil et page Partages : la proposition, à ouvrir pour la lire (pas d'acceptation groupée).
        $this->actingAs($lea)->get(route('dashboard'))->assertOk()->assertSee('vous propose une demi-pension sur')->assertSee(route('half-leases.show', $lease), false);
        $this->actingAs($lea)->get(route('sharing.index'))->assertOk()->assertSee('data-half-lease-invitation', false);
        $this->actingAs($lea)->post(route('invitations.accept-all'));
        $this->assertSame('proposed', $lease->fresh()->status, '« Tout accepter » laisse les demi-pensions de côté.');
        $this->actingAs($lea)->get(route('half-leases.show', $lease))->assertOk()->assertSee('data-half-lease-accept', false)->assertSee('Balades le week-end avec accord.');
        // Un tiers ne voit pas la fiche.
        $this->actingAs($this->user())->get(route('half-leases.show', $lease))->assertNotFound();

        $this->actingAs($lea)->post(route('invitations.respond', $lease->invitation_id), ['decision' => 'decline'])->assertSessionHas('success');
        $this->assertSame('declined', $lease->fresh()->status);
        $this->assertContains('Demi-pension refusée : Uni', $this->titles($owner));
        $this->assertSame(0, HorseAccessGrant::count());
    }

    public function test_acceptance_from_the_app_applies_days_now_and_blocks_a_taken_day(): void
    {
        [$owner, $horse] = $this->setUpOwner();
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $this->propose($owner, $horse, ['starts_on' => '2026-10-17', 'trial_ends_on' => '2026-10-19']);
        $lease = HalfLease::sole();
        $this->actingAs($lea)->post(route('invitations.respond', $lease->invitation_id), ['decision' => 'accept'])->assertRedirect(route('half-leases.show', $lease));
        $this->assertSame([2 => $lea->id, 4 => $lea->id], RidingDay::pluck('user_id', 'weekday')->all(), 'Commencée : jours inscrits tout de suite.');

        // Deuxième demi-pension : le mardi est déjà pris.
        $this->propose($owner, $horse, ['name' => 'Inès ROY', 'email' => 'ines@exemple.fr', 'weekdays' => [2, 6]])->assertSessionHasErrors('weekdays');
        $this->propose($owner, $horse, ['name' => 'Inès ROY', 'email' => 'ines@exemple.fr', 'weekdays' => [6]])->assertSessionHasNoErrors();
        $this->propose($owner, $horse, ['email' => 'lea@exemple.fr', 'weekdays' => [7]])->assertSessionHasErrors('email');
        // Proposition retirée avant sa réponse : le lien ne marche plus.
        $ines = HalfLease::where('email', 'ines@exemple.fr')->sole();
        $this->actingAs($owner)->post(route('half-leases.cancel', $ines))->assertRedirect(route('horses.sharing', $horse));
        $this->assertSame('cancelled', $ines->fresh()->status);
        $this->assertFalse($ines->invitation->fresh()->isPending());

        // Fin de la période d'essai : rappel aux deux, une fois.
        $this->artisan('reminders:send');
        $this->artisan('reminders:send');
        $this->assertSame(1, collect($this->titles($lea))->filter(fn ($t) => $t === 'Fin de la période d\'essai : Uni')->count());
        $this->assertContains('Fin de la période d\'essai : Uni', $this->titles($owner));
    }

    public function test_owner_edits_days_and_the_week_page_stays_in_sync(): void
    {
        [$owner, $horse] = $this->setUpOwner();
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $this->propose($owner, $horse, ['starts_on' => '2026-10-01']);
        $lease = HalfLease::sole();
        $this->actingAs($lea)->post(route('invitations.respond', $lease->invitation_id), ['decision' => 'accept']);
        $this->fresh();

        $this->actingAs($owner)->get(route('half-leases.edit', $lease))->assertOk();
        $this->actingAs($owner)->put(route('half-leases.update', $lease), [
            'name' => 'Léa DURAND', 'starts_on' => '2026-10-01', 'notice_days' => 30, 'weekdays' => [3, 6], 'monthly_amount' => '200', 'payment_day' => 5,
            'permissions' => Perm::halfLeasePreset(),
        ])->assertRedirect(route('half-leases.show', $lease));
        $this->assertSame([3 => $lea->id, 6 => $lea->id], RidingDay::pluck('user_id', 'weekday')->all());
        $this->assertContains('Demi-pension modifiée : Uni', $this->titles($lea));
        $this->assertSame('200.00', $lease->fresh()->monthly_amount);
        // La demi-pension ne modifie pas la fiche.
        $this->actingAs($lea)->get(route('half-leases.edit', $lease))->assertForbidden();

        // Semaine type changée sur la page Jours de monte : la fiche suit.
        $this->fresh();
        $this->actingAs($owner)->put(route('horses.riding-days.update', $horse), ['days' => [3 => $lea->id, 5 => $lea->id, 6 => $owner->id]])->assertSessionHasNoErrors();
        $this->assertSame([3, 5], $lease->fresh()->weekdays);
    }

    public function test_end_with_notice_closes_access_and_frees_days(): void
    {
        [$owner, $horse] = $this->setUpOwner();
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $this->propose($owner, $horse, ['starts_on' => '2026-10-01']);
        $lease = HalfLease::sole();
        $this->actingAs($lea)->post(route('invitations.respond', $lease->invitation_id), ['decision' => 'accept']);
        $this->fresh();
        // Préavis d'un mois proposé ; la demi-pension met fin elle-même.
        $this->actingAs($lea)->get(route('half-leases.show', $lease))->assertSee('value="2026-11-16"', false);
        $this->actingAs($lea)->post(route('half-leases.end', $lease), ['last_day' => '2026-10-10'])->assertSessionHasErrors('last_day');
        $this->actingAs($lea)->post(route('half-leases.end', $lease), ['last_day' => '2026-11-16'])->assertSessionHas('success');
        $this->assertSame('2026-11-17 00:00', $lease->fresh()->grant->expires_at->format('Y-m-d H:i'));
        $this->assertContains('Fin de la demi-pension : Uni', $this->titles($owner));

        Carbon::setTestNow('2026-11-17 07:00');
        $this->artisan('reminders:send');
        $this->assertSame('ended', $lease->fresh()->status);
        $this->assertSame(0, RidingDay::where('user_id', $lea->id)->count(), 'Ses jours redeviennent libres.');
        $this->fresh();
        $this->assertFalse(app(HorseAccess::class)->can($lea, $horse, Perm::HORSE_VIEW));
        $this->actingAs($owner)->get(route('half-leases.show', $lease))->assertOk()->assertSee('Terminée');
    }

    public function test_revoked_access_ends_the_half_lease_and_shares_keep_their_label(): void
    {
        [$owner, $horse] = $this->setUpOwner();
        $lea = $this->user(['name' => 'Léa DURAND', 'email' => 'lea@exemple.fr']);
        $this->propose($owner, $horse, ['starts_on' => '2026-10-01']);
        $lease = HalfLease::sole();
        $this->actingAs($lea)->post(route('invitations.respond', $lease->invitation_id), ['decision' => 'accept']);
        $this->actingAs($owner)->post(route('horses.sharing.grants.revoke', [$horse, $lease->fresh()->grant_id]));
        $this->assertSame(['ended', '2026-10-17'], [$lease->fresh()->status, $lease->fresh()->ends_on->toDateString()]);
        $this->assertSame(0, RidingDay::count());

        // Partage ordinaire : le libellé choisi reste après l'acceptation (« Partage » avant 2.46).
        $coach = $this->user(['email' => 'coach@exemple.fr']);
        $this->actingAs($owner)->post(route('horses.sharing.invite', $horse), ['email' => 'coach@exemple.fr', 'label' => 'Coach', 'permissions' => [Perm::SESSIONS_VIEW]]);
        $invitation = Invitation::where('email', 'coach@exemple.fr')->sole();
        $this->actingAs($coach)->post(route('invitations.respond', $invitation), ['decision' => 'accept']);
        $this->assertSame('Coach', HorseAccessGrant::where('user_id', $coach->id)->value('label'));
    }
}
