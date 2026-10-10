<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected $casts = ['properties' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
