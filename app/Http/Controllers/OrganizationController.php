<?php

namespace App\Http\Controllers;

use App\Models\HorseOrganizationAssignment;
use App\Models\Invitation;
use App\Services\Audit;
use App\Services\OrganizationService;
use App\Support\Perm;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function switch(Request $request)
    {
        $id = (int) $request->input('organization_id');
        abort_unless($request->user()->memberships()->where('organization_id', $id)->exists(), 403);
        $request->user()->forceFill(['current_organization_id' => $id])->save();

        return redirect()->route('dashboard');
    }

    public function create()
    {
        return view('organizations.create');
    }

    public function store(Request $request, OrganizationService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);
        $service->createStable($request->user(), $data);

        return redirect()->route('organization.show')->with('success', 'Écurie créée. Choisissez une offre « Écurie » pour inviter des membres.');
    }

    public function show(Request $request)
    {
        $org = $this->currentOrganization();
        $user = $request->user();
        $perms = $user->orgPermissions($org);

        return view('organizations.show', [
            'org' => $org,
            'perms' => $perms,
            'members' => $org->members()->with(['user', 'role'])->get(),
            'roles' => $org->availableRoles(),
            'customRoles' => $org->roles()->with('permissions')->get(),
            'invitations' => Invitation::pending()->where('type', 'organization_member')->where('organization_id', $org->id)->with('role')->get(),
            'assignments' => HorseOrganizationAssignment::with('horse')->where('organization_id', $org->id)->whereIn('status', ['pending', 'active'])->latest()->get(),
            'pastAssignments' => HorseOrganizationAssignment::with('horse')->where('organization_id', $org->id)->where('status', 'ended')->latest('ends_at')->limit(20)->get(),
            'horseCount' => $org->horses()->whereNull('archived_at')->count(),
            'ent' => $this->entitlements()->for($org),
            'canManage' => in_array(Perm::ORG_MANAGE, $perms, true),
            'canMembers' => in_array(Perm::MEMBERS_MANAGE, $perms, true),
        ]);
    }

    public function update(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::ORG_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);
        $org->update($data);
        Audit::log('organization.updated', $org, array_keys($org->getChanges()), $org->id);

        return back()->with('success', 'Informations enregistrées.');
    }
}
