<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseOrganizationAssignment;
use App\Models\Invitation;
use App\Models\RidingSession;
use App\Notifications\AppNotification;
use App\Services\Audit;
use App\Services\HorseAccess;
use App\Services\InvitationService;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Partage d'un cheval (demi-pension, cavalier) avec droits explicites,
 * période d'accès et révocation immédiate côté serveur.
 */
class HorseSharingController extends Controller
{
    public function overview(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        $managed = $access->accessibleQuery($user)->get()->filter(fn ($h) => in_array(Perm::HORSE_MANAGE, $access->permissions($user, $h), true));

        return view('sharing.index', [
            'managed' => $managed->load(['accessGrants' => fn ($q) => $q->active()->with('user')]),
            'received' => HorseAccessGrant::active()->where('user_id', $user->id)->with(['horse', 'grantor'])->get(),
            'invitations' => Invitation::pending()->where('email', $user->email)->with(['horse', 'organization', 'inviter'])->get(),
        ]);
    }

    public function show(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);

        return view('sharing.horse', [
            'horse' => $horse,
            'grants' => $horse->accessGrants()->with(['user', 'grantor'])->latest()->get(),
            'invitations' => $horse->invitations()->pending()->latest()->get(),
            'assignments' => $horse->assignments()->with('organization')->latest()->get(),
            'labels' => Perm::horseLabels(),
            'presets' => ['half_lease' => Perm::halfLeasePreset(), 'boarding' => Perm::boardingPreset()],
            'kinds' => HorseOrganizationAssignment::KINDS,
            'sharingIncluded' => $this->entitlements()->hasFeature($horse->organization, 'sharing'),
        ]);
    }

    public function invite(Request $request, Horse $horse, InvitationService $invitations)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'label' => ['nullable', 'string', 'max:60'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Perm::horseKeys())],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:today', 'after_or_equal:starts_at'],
        ]);
        $invitations->inviteToHorse($horse, $request->user(), $data['email'], $data['permissions'] ?? [], $data['label'] ?? null, $data['starts_at'] ?? null, $data['expires_at'] ?? null);

        return back()->with('success', 'Invitation envoyée. L\'accès sera actif dès son acceptation.');
    }

    public function revokeInvitation(Horse $horse, Invitation $invitation)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $invitation->update(['revoked_at' => now()]);
        Audit::log('invitation.revoked', $invitation, [], $horse->organization_id);

        return back()->with('success', 'Invitation annulée.');
    }

    public function updateGrant(Request $request, Horse $horse, HorseAccessGrant $grant)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        abort_if($grant->revoked_at, 422, 'Accès déjà révoqué.');
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Perm::horseKeys())],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $before = $grant->permissions;
        $grant->update([
            'label' => $data['label'] ?? $grant->label,
            'permissions' => array_values(array_unique([Perm::HORSE_VIEW, ...($data['permissions'] ?? [])])),
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        Audit::log('grant.updated', $grant, ['before' => $before, 'after' => $grant->permissions, 'expires_at' => $grant->expires_at?->toIso8601String()], $horse->organization_id);

        return back()->with('success', 'Droits mis à jour. Ils s\'appliquent immédiatement.');
    }

    public function revokeGrant(Request $request, Horse $horse, HorseAccessGrant $grant)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $grant->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id]);
        Audit::log('grant.revoked', $grant, ['user_id' => $grant->user_id], $horse->organization_id);
        $grant->user?->notify(new AppNotification('access_revoked', 'Accès retiré', 'Votre accès au dossier de '.$horse->shortName().' a été retiré par le propriétaire.', null, false));

        return back()->with('success', 'Accès révoqué immédiatement en ligne. Les copies hors ligne de cette personne seront purgées à la prochaine connexion de son appareil.');
    }

    /** Historique des accès et des modifications du dossier. */
    public function history(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $logs = AuditLog::with('user')->where(function ($q) use ($horse) {
            $q->where(fn ($w) => $w->where('subject_type', $horse->getMorphClass())->where('subject_id', $horse->id))
                ->orWhere(fn ($w) => $w->where('subject_type', (new HorseAccessGrant)->getMorphClass())->whereIn('subject_id', $horse->accessGrants()->pluck('id')))
                ->orWhere(fn ($w) => $w->where('subject_type', (new Invitation)->getMorphClass())->whereIn('subject_id', $horse->invitations()->pluck('id')))
                ->orWhere(fn ($w) => $w->where('subject_type', (new CareRecord)->getMorphClass())->whereIn('subject_id', $horse->cares()->withTrashed()->pluck('id')))
                ->orWhere(fn ($w) => $w->where('subject_type', (new HorseOrganizationAssignment)->getMorphClass())->whereIn('subject_id', $horse->assignments()->pluck('id')));
        })->latest('created_at')->paginate(50);

        return view('sharing.history', [
            'horse' => $horse,
            'logs' => $logs,
            'recentSessions' => RidingSession::withTrashed()->where('horse_id', $horse->id)->with(['creator'])->latest('updated_at')->limit(20)->get(),
        ]);
    }
}
