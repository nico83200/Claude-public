<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\Audit;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Rôles personnalisés d'une écurie. */
class OrganizationRoleController extends Controller
{
    public function store(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::MEMBERS_MANAGE);
        $data = $this->validated($request);
        $role = Role::create(['organization_id' => $org->id, 'key' => 'custom-'.Str::lower(Str::random(8)), 'name' => $data['name'], 'is_system' => false]);
        $role->syncPermissionKeys($data['permissions'] ?? []);
        Audit::log('role.created', $role, ['permissions' => $data['permissions'] ?? []], $org->id);

        return back()->with('success', 'Rôle créé.');
    }

    public function update(Request $request, Role $role)
    {
        $org = $this->currentOrganization();
        abort_unless($role->organization_id === $org->id, 404);
        $this->authorizeOrg($org, Perm::MEMBERS_MANAGE);
        $data = $this->validated($request);
        $role->update(['name' => $data['name']]);
        $role->syncPermissionKeys($data['permissions'] ?? []);
        Audit::log('role.updated', $role, ['permissions' => $data['permissions'] ?? []], $org->id);

        return back()->with('success', 'Rôle mis à jour.');
    }

    public function destroy(Role $role)
    {
        $org = $this->currentOrganization();
        abort_unless($role->organization_id === $org->id, 404);
        $this->authorizeOrg($org, Perm::MEMBERS_MANAGE);
        if ($org->members()->where('role_id', $role->id)->exists()) {
            return back()->with('error', 'Ce rôle est attribué à des membres : modifiez d\'abord leur rôle.');
        }
        $role->delete();

        return back()->with('success', 'Rôle supprimé.');
    }

    private function validated(Request $request): array
    {
        $allowed = [...Perm::horseKeys(), ...array_diff(array_keys(Perm::orgLabels()), [Perm::BILLING_MANAGE])];

        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in($allowed)],
        ]);
    }
}
