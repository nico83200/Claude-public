<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_system' => 'boolean'];

    private ?array $permissionKeysCache = null;

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function permissionKeys(): array
    {
        return $this->permissionKeysCache ??= ($this->relationLoaded('permissions') ? $this->permissions->pluck('key')->all() : $this->permissions()->pluck('key')->all());
    }

    public function syncPermissionKeys(array $keys): void
    {
        $this->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));
        $this->permissionKeysCache = null;
    }

    public static function system(string $key): self
    {
        return static::whereNull('organization_id')->where('key', $key)->firstOrFail();
    }
}
