<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Services\Audit;
use App\Services\InvitationService;
use App\Support\Perm;
use Illuminate\Http\Request;

class OrganizationMemberController extends Controller
{
    public function invite(Request $request, InvitationService $invitations)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::MEMBERS_MANAGE);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'integer'],
        ]);
        $role = $this->roleFor($org->id, (int) $data['role_id']);
        $invitations->inviteToOrganization($org, $request->user(), $data['email'], $role);

        return back()->with('success', 'Invitation envoyée à '.$data['email'].'.');
    }

    public function update(Request $request, OrganizationMember $member)
    {
        $org = $this->currentOrganization();
        abort_unless($member->organization_id === $org->id, 404);
        $this->authorizeOrg($org, Perm::MEMBERS_MANAGE);
        $data = $request->validate(['role_id' => ['required', 'integer'], 'title' => ['nullable', 'string', 'max:80']]);
        $role = $this->roleFor($org->id, (int) $data['role_id']);

        abort_if($member->user_id === $org->owner_id, 422, 'Le rôle du propriétaire de l\'espace ne peut pas être modifié.');
        abort_if($role->key === 'owner' && $role->is_system, 422, 'Le rôle de propriétaire ne peut pas être attribué.');

        $member->update(['role_id' => $role->id, 'title' => $data['title'] ?? null]);
        Audit::log('member.role_changed', $member, ['user_id' => $member->user_id, 'role' => $role->key], $org->id);

        return back()->with('success', 'Rôle mis à jour.');
    }

    public function destroy(Request $request, OrganizationMember $member)
    {
        $org = $this->currentOrganization();
        abort_unless($member->organization_id === $org->id, 404);
        $isSelf = $member->user_id === $request->user()->id;
        if (! $isSelf) {
            $this->authorizeOrg($org, Perm::MEMBERS_MANAGE);
        }
        abort_if($member->user_id === $org->owner_id, 422, 'Le propriétaire ne peut pas quitter son propre espace.');

        $member->delete();
        Audit::log('member.removed', $org, ['user_id' => $member->user_id, 'self' => $isSelf], $org->id);

        if ($isSelf) {
            $request->user()->forceFill(['current_organization_id' => $request->user()->personalOrganization()?->id])->save();

            return redirect()->route('dashboard')->with('success', 'Vous avez quitté l\'espace.');
        }

        return back()->with('success', 'Membre retiré. Ses accès sont supprimés immédiatement.');
    }

    public function revokeInvitation(Invitation $invitation)
    {
        $org = $this->currentOrganization();
        abort_unless($invitation->organization_id === $org->id && $invitation->type === 'organization_member', 404);
        $this->authorizeOrg($org, Perm::MEMBERS_MANAGE);
        $invitation->update(['revoked_at' => now()]);

        return back()->with('success', 'Invitation annulée.');
    }

    private function roleFor(int $orgId, int $roleId): Role
    {
        return Role::where('id', $roleId)->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $orgId))->firstOrFail();
    }
}
