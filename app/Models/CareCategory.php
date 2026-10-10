<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareCategory extends Model
{
    protected $guarded = ['id'];

    public function scopeVisibleTo($q, ?int $organizationId)
    {
        return $q->whereNull('organization_id')->orWhere('organization_id', $organizationId);
    }
}
