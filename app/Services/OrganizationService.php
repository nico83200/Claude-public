<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationService
{
    /** Crée l'espace personnel d'un nouvel utilisateur. */
    public function createPersonal(User $user): Organization
    {
        return DB::transaction(function () use ($user) {
            $org = Organization::create([
                'name' => 'Espace de '.$user->name,
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

    public function createStable(User $owner, array $data): Organization
    {
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
