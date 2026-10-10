<?php

namespace App\Services;

use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Perm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invitations par email : le jeton n'est stocké que sous forme de hash.
 * Un accès n'est créé qu'après acceptation par le compte correspondant à l'email invité.
 */
class InvitationService
{
    public function __construct(private Entitlements $entitlements, private OrganizationService $organizations) {}

    public function inviteToHorse(Horse $horse, User $inviter, string $email, array $permissions, ?string $label, $startsAt, $expiresAt): array
    {
        $org = $horse->organization;
        if (! $this->entitlements->hasFeature($org, 'sharing')) {
            throw ValidationException::withMessages(['email' => 'Le partage n\'est pas inclus dans l\'offre de cet espace.']);
        }
        $used = HorseAccessGrant::active()->whereIn('horse_id', $org->horses()->pluck('id'))->count()
            + Invitation::pending()->where('type', 'horse_access')->whereIn('horse_id', $org->horses()->pluck('id'))->count();
        if (! $this->entitlements->withinLimit($org, 'max_invitations', $used)) {
            throw ValidationException::withMessages(['email' => 'Le nombre maximal de partages de votre offre est atteint.']);
        }
        $email = strtolower($email);
        if ($email === strtolower($inviter->email)) {
            throw ValidationException::withMessages(['email' => 'Vous ne pouvez pas vous inviter vous-même.']);
        }

        $permissions = array_values(array_unique([Perm::HORSE_VIEW, ...array_intersect($permissions, Perm::horseKeys())]));

        return $this->create([
            'type' => 'horse_access', 'email' => $email, 'horse_id' => $horse->id, 'organization_id' => $org->id,
            'permissions' => $permissions, 'access_starts_at' => $startsAt, 'access_expires_at' => $expiresAt,
            'invited_by' => $inviter->id,
        ], "Accès au dossier de {$horse->shortName()}", "{$inviter->name} vous invite à accéder au dossier du cheval {$horse->shortName()}".($label ? " ({$label})" : '').'.');
    }

    public function inviteToOrganization(Organization $org, User $inviter, string $email, Role $role): array
    {
        if (! $org->isPersonal() && ! $this->entitlements->hasFeature($org, 'stable')) {
            throw ValidationException::withMessages(['email' => 'La gestion des membres n\'est pas incluse dans l\'offre de cet espace.']);
        }
        $count = $org->members()->count() + Invitation::pending()->where('type', 'organization_member')->where('organization_id', $org->id)->count();
        if (! $this->entitlements->withinLimit($org, 'max_members', $count)) {
            throw ValidationException::withMessages(['email' => 'Le nombre maximal de membres de votre offre est atteint.']);
        }
        if ($role->key === 'owner' && $role->is_system) {
            throw ValidationException::withMessages(['role_id' => 'Le rôle de propriétaire ne peut pas être attribué par invitation.']);
        }
        $email = strtolower($email);
        if ($org->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Cette personne est déjà membre de l\'espace.']);
        }

        return $this->create([
            'type' => 'organization_member', 'email' => $email, 'organization_id' => $org->id, 'role_id' => $role->id,
            'invited_by' => $inviter->id,
        ], "Invitation à rejoindre {$org->name}", "{$inviter->name} vous invite à rejoindre l'espace {$org->name} en tant que « {$role->name} ».");
    }

    /** @return array{0: Invitation, 1: string} invitation + jeton en clair (à n'envoyer que par email) */
    private function create(array $data, string $title, string $body): array
    {
        $token = Str::random(48);
        $invitation = Invitation::create($data + ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(14)]);
        $url = route('invitations.show', $token);

        $existing = User::where('email', $data['email'])->first();
        $notification = new AppNotification('invitation', $title, $body, $url);
        $existing ? $existing->notify($notification) : Notification::route('mail', $data['email'])->notify($notification);

        Audit::log('invitation.created', $invitation, ['email' => $data['email'], 'type' => $data['type']], $data['organization_id'] ?? null);

        return [$invitation, $token];
    }

    public function findByToken(string $token): ?Invitation
    {
        return Invitation::where('token_hash', hash('sha256', $token))->first();
    }

    public function accept(Invitation $invitation, User $user): void
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['invitation' => 'Cette invitation n\'est plus valable.']);
        }
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages(['invitation' => 'Cette invitation a été envoyée à une autre adresse email ('.$invitation->email.').']);
        }

        DB::transaction(function () use ($invitation, $user) {
            if ($invitation->type === 'horse_access') {
                HorseAccessGrant::create([
                    'horse_id' => $invitation->horse_id, 'user_id' => $user->id, 'permissions' => $invitation->permissions,
                    'starts_at' => $invitation->access_starts_at, 'expires_at' => $invitation->access_expires_at,
                    'granted_by' => $invitation->invited_by, 'invitation_id' => $invitation->id, 'label' => 'Partage',
                ]);
            } else {
                $this->organizations->addMember($invitation->organization, $user, $invitation->role);
            }
            $invitation->update(['accepted_at' => now(), 'accepted_by' => $user->id]);
        });

        Audit::log('invitation.accepted', $invitation, ['user' => $user->email], $invitation->organization_id);
        $invitation->inviter?->notify(new AppNotification('invitation_accepted', 'Invitation acceptée', "{$user->name} a accepté votre invitation.", null, false));
    }
}
