<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExerciseTag extends Model
{
    protected $guarded = ['id'];

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'exercise_tag_assignments', 'exercise_tag_id', 'exercise_id');
    }
}
