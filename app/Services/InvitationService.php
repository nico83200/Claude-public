<?php

namespace App\Services;

use App\Models\HalfLease;
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

    /** Partages en cours (accès actifs et invitations en attente) sur les chevaux de l'espace. */
    public function horseSharesUsed(Organization $org): int
    {
        $horseIds = $org->horses()->pluck('id');

        return HorseAccessGrant::current()->whereIn('horse_id', $horseIds)->count()
            + Invitation::pending()->where('type', 'horse_access')->whereIn('horse_id', $horseIds)->count();
    }

    public function inviteToHorse(Horse $horse, User $inviter, string $email, array $permissions, ?string $label, $startsAt, $expiresAt): array
    {
        $org = $horse->organization;
        $email = strtolower($email);
        $this->ensureCanShare($org, $inviter, $email);

        $permissions = array_values(array_unique([Perm::HORSE_VIEW, ...array_intersect($permissions, Perm::shareableKeys())]));

        return $this->create([
            'type' => 'horse_access', 'email' => $email, 'horse_id' => $horse->id, 'organization_id' => $org->id,
            'permissions' => $permissions, 'label' => $label ? mb_substr(trim($label), 0, 60) : null, 'access_starts_at' => $startsAt, 'access_expires_at' => $expiresAt,
            'invited_by' => $inviter->id,
        ], "Accès au dossier de {$horse->shortName()}", "{$inviter->name} vous invite à accéder au dossier du cheval {$horse->shortName()}".($label ? " ({$label})" : '').'.');
    }

    /** Proposition de demi-pension : l'invitation porte les conditions ; l'accès s'ouvre à l'acceptation, jusqu'au soir du dernier jour. */
    public function inviteHalfLease(HalfLease $lease, User $inviter): array
    {
        $horse = $lease->horse;
        $this->ensureCanShare($horse->organization, $inviter, strtolower($lease->email));
        $halfLeases = app(HalfLeases::class);

        return $this->create([
            'type' => 'horse_access', 'email' => strtolower($lease->email), 'horse_id' => $horse->id, 'organization_id' => $horse->organization_id,
            'permissions' => $halfLeases->permissions($lease), 'label' => 'Demi-pension', 'access_starts_at' => null, 'access_expires_at' => $lease->ends_on?->copy()->addDay(),
            'invited_by' => $inviter->id,
        ], 'Proposition de demi-pension : '.$horse->shortName(), $inviter->name.' vous propose une demi-pension sur '.$horse->shortName().' : '.$halfLeases->summary($lease).'.');
    }

    private function ensureCanShare(Organization $org, User $inviter, string $email): void
    {
        if (! $this->entitlements->hasFeature($org, 'sharing')) {
            throw ValidationException::withMessages(['email' => 'Le partage n\'est pas inclus dans l\'offre de cet espace.']);
        }
        if (! $this->entitlements->withinLimit($org, 'max_invitations', $this->horseSharesUsed($org))) {
            throw ValidationException::withMessages(['email' => 'Le nombre maximal de partages de votre offre est atteint.']);
        }
        if ($email === strtolower($inviter->email)) {
            throw ValidationException::withMessages(['email' => 'Vous ne pouvez pas vous inviter vous-même.']);
        }
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

    /** @return array{0: Invitation, 1: string} invitation + jeton en clair (envoyé par e-mail, et montré une fois à l'invitant pour un message ; lié à l'adresse invitée) */
    private function create(array $data, string $title, string $body): array
    {
        $token = Str::random(48);
        $invitation = Invitation::create($data + ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(14)]);
        $this->send($data['email'], $title, $body, route('invitations.show', $token));

        Audit::log('invitation.created', $invitation, ['email' => $data['email'], 'type' => $data['type']], $data['organization_id'] ?? null);

        return [$invitation, $token];
    }

    /**
     * Relance d'une invitation en attente : nouveau lien (l'ancien ne marche plus), validité repartie pour 14 jours.
     * Retourne le jeton en clair pour un envoi par message (le lien reste lié à l'adresse invitée).
     */
    public function resend(Invitation $invitation, User $by): string
    {
        $token = Str::random(48);
        $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(14)]);
        $invitation->loadMissing(['horse', 'organization']);
        $place = $invitation->horse ? 'au dossier de '.$invitation->horse->shortName() : 'à l\'espace '.$invitation->organization?->name;
        $lease = HalfLease::where('invitation_id', $invitation->id)->first();
        $this->send($invitation->email, 'Rappel : invitation de '.$by->name, $lease
            ? $by->name.' vous propose une demi-pension sur '.$invitation->horse?->shortName().' : '.app(HalfLeases::class)->summary($lease).'.'
            : $by->name.' vous invite à accéder '.$place.' sur Jack&Cie.', route('invitations.show', $token));
        Audit::log('invitation.resent', $invitation, ['email' => $invitation->email], $invitation->organization_id);

        return $token;
    }

    private function send(string $email, string $title, string $body, string $url): void
    {
        $existing = User::where('email', $email)->first();
        $notification = new AppNotification('invitation', $title, $body, $url);
        $existing ? $existing->notify($notification) : Notification::route('mail', $email)->notify($notification);
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

        $lease = HalfLease::where('invitation_id', $invitation->id)->where('status', 'proposed')->first();
        DB::transaction(function () use ($invitation, $user, $lease) {
            if ($invitation->type === 'horse_access') {
                $grant = HorseAccessGrant::create([
                    'horse_id' => $invitation->horse_id, 'user_id' => $user->id, 'permissions' => $invitation->permissions,
                    'starts_at' => $invitation->access_starts_at, 'expires_at' => $invitation->access_expires_at,
                    'granted_by' => $invitation->invited_by, 'invitation_id' => $invitation->id, 'label' => $invitation->label ?: 'Partage',
                ]);
            } else {
                $this->organizations->addMember($invitation->organization, $user, $invitation->role);
            }
            $invitation->update(['accepted_at' => now(), 'accepted_by' => $user->id]);
            // Demi-pension : la fiche passe en cours (jours habituels, prévenir le propriétaire).
            if ($lease && isset($grant)) {
                app(HalfLeases::class)->accepted($lease, $user, $grant);
            }
        });

        Audit::log('invitation.accepted', $invitation, ['user' => $user->email], $invitation->organization_id);
        if (! $lease) {
            $invitation->inviter?->notify(new AppNotification('invitation_accepted', 'Invitation acceptée', "{$user->name} a accepté votre invitation.", null, false));
        }
    }

    /** Refus par la personne invitée (lien reçu ou appli) : une demi-pension proposée passe à « refusée ». */
    public function decline(Invitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);
        app(HalfLeases::class)->declined($invitation);
    }
}
