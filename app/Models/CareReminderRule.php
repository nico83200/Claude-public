<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareReminderRule extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'interval_value' => 'integer', 'notice_days' => 'integer'];

    public const UNITS = ['weeks' => 'semaines', 'months' => 'mois'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function category()
    {
        return $this->belongsTo(CareCategory::class, 'care_category_id');
    }
}
