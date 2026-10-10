<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationMember extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['joined_at' => 'datetime'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
