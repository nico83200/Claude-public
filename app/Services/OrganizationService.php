<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    /** Crée l'espace personnel d'un nouvel utilisateur. */
    public function createPersonal(User $user): Organization
    {
        return DB::transaction(function () use ($user) {
            $org = Organization::create([
                // Espace personnel au nom du cavalier (« Nicolas LEFEVRE »), suivi des changements de nom (syncPersonalName).
                'name' => $user->name,
                'slug' => $this->uniqueSlug('perso-'.$user->name),
                'type' => 'personal',
                'owner_id' => $user->id,
                'email' => $user->email,
            ]);
            $this->addMember($org, $user, 'owner');
            $user->forceFill(['current_organization_id' => $org->id])->save();

            return $org;
        });
    }

    /** Nom du compte modifié : l'espace personnel suit, sauf s'il a été renommé autrement. */
    public function syncPersonalName(User $user, ?string $previous): void
    {
        $org = $user->personalOrganization();
        if ($org && $previous !== null && in_array($org->name, [$previous, 'Espace de '.$previous], true)) {
            $org->update(['name' => $user->name]);
        }
    }

    /**
     * Un compte = son espace personnel (où il peut être cavalier), et au plus une écurie (offre Écurie).
     * Les espaces où il est seulement invité (demi-pension, écurie d'un tiers) ne comptent pas.
     */
    public function createStable(User $owner, array $data): Organization
    {
        if ($owner->ownedStable()) {
            throw ValidationException::withMessages(['name' => 'Votre compte gère déjà une écurie : un compte ne peut avoir qu\'un seul espace écurie.']);
        }

        return DB::transaction(function () use ($owner, $data) {
            $org = Organization::create(array_merge($data, [
                'slug' => $this->uniqueSlug($data['name']),
                'type' => 'stable',
                'owner_id' => $owner->id,
            ]));
            $this->addMember($org, $owner, 'owner');
            $owner->forceFill(['current_organization_id' => $org->id])->save();
            Audit::log('organization.created', $org, ['name' => $org->name], $org->id);

            return $org;
        });
    }

    public function addMember(Organization $org, User $user, string|Role $role, ?string $title = null): OrganizationMember
    {
        $role = $role instanceof Role ? $role : Role::system($role);

        return OrganizationMember::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $user->id],
            ['role_id' => $role->id, 'title' => $title, 'joined_at' => now()],
        );
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'espace';
        $candidate = $slug;
        while (Organization::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.Str::lower(Str::random(5));
        }

        return $candidate;
    }
}
