<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\HorseOwnership;
use App\Models\User;
use App\Services\Audit;
use App\Support\Perm;
use Illuminate\Http\Request;

/**
 * Propriétaires et détenteurs. Un propriétaire déclaré avec un compte obtient
 * l'accès complet ; un nom sans compte est purement informatif.
 */
class OwnershipController extends Controller
{
    public function store(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $data = $request->validate([
            'email' => ['nullable', 'email', 'required_without:owner_name'],
            'owner_name' => ['nullable', 'string', 'max:150', 'required_without:email'],
            'role' => ['required', 'in:owner,co_owner,keeper'],
            'share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'started_on' => ['nullable', 'date'],
        ]);
        $user = ! empty($data['email']) ? User::where('email', strtolower($data['email']))->first() : null;
        if (! empty($data['email']) && ! $user) {
            return back()->withErrors(['email' => 'Aucun compte avec cette adresse. Utilisez le partage pour l\'inviter, ou saisissez simplement son nom.'])->withInput();
        }
        $ownership = HorseOwnership::create([
            'horse_id' => $horse->id, 'user_id' => $user?->id, 'owner_name' => $user ? null : $data['owner_name'],
            'role' => $data['role'], 'share_percent' => $data['share_percent'] ?? null, 'started_on' => $data['started_on'] ?? now()->toDateString(),
        ]);
        Audit::log('ownership.added', $horse, ['ownership' => $ownership->id, 'user' => $user?->email, 'role' => $data['role']], $horse->organization_id);

        return back()->with('success', 'Propriétaire / détenteur ajouté.');
    }

    public function end(Request $request, Horse $horse, HorseOwnership $ownership)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $active = $horse->ownerships()->whereNull('ended_on')->whereIn('role', ['owner', 'co_owner'])->whereNotNull('user_id')->where('id', '!=', $ownership->id)->exists();
        $managedByOrg = $request->user()->memberships->contains('organization_id', $horse->organization_id);
        if ($ownership->user_id === $request->user()->id && ! $active && ! $managedByOrg) {
            return back()->with('error', 'Vous êtes le seul propriétaire avec un compte : ajoutez d\'abord le nouveau propriétaire.');
        }
        $ownership->update(['ended_on' => now()->toDateString()]);
        Audit::log('ownership.ended', $horse, ['ownership' => $ownership->id], $horse->organization_id);

        return back()->with('success', 'Fin de propriété enregistrée (historique conservé).');
    }
}
