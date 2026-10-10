<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentAdministration extends Model
{
    protected $guarded = ['id'];

    use Concerns\HasClientUuid;

    protected $casts = ['administered_at' => 'datetime'];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}
