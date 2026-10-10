<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\HorseLocation;
use App\Models\HorseOrganizationAssignment;
use App\Models\Organization;
use App\Notifications\AppNotification;
use App\Services\Audit;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rattachement d'un cheval à une écurie (pension, travail, soins) sans
 * duplication du dossier. Le propriétaire choisit les droits accordés à
 * l'écurie ; l'écurie accepte ou refuse ; chacun peut y mettre fin.
 * L'historique saisi reste attaché au cheval.
 */
class AssignmentController extends Controller
{
    public function request(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $data = $request->validate([
            'stable_code' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(array_keys(HorseOrganizationAssignment::KINDS))],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Perm::horseKeys())],
        ]);
        $org = Organization::where('slug', trim($data['stable_code']))->where('type', 'stable')->whereNull('suspended_at')->first();
        if (! $org) {
            return back()->withErrors(['stable_code' => 'Aucune écurie ne correspond à ce code. Demandez-le au gérant (page Organisation).'])->withInput();
        }
        if ($org->id === $horse->organization_id) {
            return back()->withErrors(['stable_code' => 'Ce cheval est déjà géré par cette écurie.']);
        }
        if (HorseOrganizationAssignment::where('horse_id', $horse->id)->where('organization_id', $org->id)->whereIn('status', ['pending', 'active'])->exists()) {
            return back()->withErrors(['stable_code' => 'Un rattachement est déjà en cours avec cette écurie.']);
        }

        $assignment = HorseOrganizationAssignment::create([
            'horse_id' => $horse->id, 'organization_id' => $org->id, 'kind' => $data['kind'],
            'permissions' => array_values(array_unique([Perm::HORSE_VIEW, ...($data['permissions'] ?? [])])),
            'status' => 'pending', 'requested_by' => $request->user()->id,
        ]);
        Audit::log('assignment.requested', $assignment, ['organization' => $org->name], $horse->organization_id);

        foreach ($org->members()->with(['user', 'role'])->get() as $m) {
            if (in_array(Perm::ORG_MANAGE, $m->role->permissionKeys(), true)) {
                $m->user->notify(new AppNotification('invitation', 'Demande de prise en charge', "{$request->user()->name} souhaite confier {$horse->shortName()} à {$org->name}.", route('organization.show')));
            }
        }

        return back()->with('success', 'Demande envoyée à '.$org->name.'.');
    }

    public function update(Request $request, Horse $horse, HorseOrganizationAssignment $assignment)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => [Rule::in(Perm::horseKeys())]]);
        $assignment->update(['permissions' => array_values(array_unique([Perm::HORSE_VIEW, ...($data['permissions'] ?? [])]))]);
        Audit::log('assignment.permissions_changed', $assignment, ['permissions' => $assignment->permissions], $horse->organization_id);

        return back()->with('success', 'Droits de l\'écurie mis à jour.');
    }

    public function accept(Request $request, HorseOrganizationAssignment $assignment)
    {
        $this->authorizeOrg($assignment->organization, Perm::ORG_MANAGE);
        abort_unless($assignment->status === 'pending', 422);
        $assignment->update(['status' => 'active', 'starts_at' => now()]);
        HorseLocation::where('horse_id', $assignment->horse_id)->whereNull('ended_on')->update(['ended_on' => now()->toDateString()]);
        HorseLocation::create(['horse_id' => $assignment->horse_id, 'organization_id' => $assignment->organization_id, 'label' => $assignment->organization->name, 'address' => trim(($assignment->organization->address_line ?? '').' '.($assignment->organization->city ?? '')) ?: null, 'started_on' => now()->toDateString()]);
        $assignment->horse->update(['current_location' => $assignment->organization->name]);
        Audit::log('assignment.accepted', $assignment, [], $assignment->organization_id);

        return back()->with('success', 'Cheval accueilli dans l\'écurie.');
    }

    public function decline(HorseOrganizationAssignment $assignment)
    {
        $this->authorizeOrg($assignment->organization, Perm::ORG_MANAGE);
        abort_unless($assignment->status === 'pending', 422);
        $assignment->update(['status' => 'declined']);

        return back()->with('success', 'Demande refusée.');
    }

    public function end(Request $request, HorseOrganizationAssignment $assignment)
    {
        $user = $request->user();
        $horse = $assignment->horse;
        $allowed = $this->horseCan($horse, Perm::HORSE_MANAGE) || $user->canInOrg($assignment->organization_id, Perm::ORG_MANAGE);
        abort_unless($allowed, 403);
        $assignment->update(['status' => 'ended', 'ends_at' => now()]);
        HorseLocation::where('horse_id', $horse->id)->where('organization_id', $assignment->organization_id)->whereNull('ended_on')->update(['ended_on' => now()->toDateString()]);
        Audit::log('assignment.ended', $assignment, [], $horse->organization_id);

        return back()->with('success', 'Prise en charge terminée. L\'écurie n\'a plus accès au dossier ; l\'historique est conservé.');
    }
}
