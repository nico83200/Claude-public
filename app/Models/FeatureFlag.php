<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['enabled_globally' => 'boolean'];

    public static function enabled(string $key): bool
    {
        return (bool) cache()->remember('flag:'.$key, 300, fn () => static::where('key', $key)->value('enabled_globally') ?? true);
    }
}
