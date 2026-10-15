<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['value' => 'array'];

    public static function get(string $key, mixed $default = null): mixed
    {
        // Valeur enveloppée : un réglage absent (null) est aussi mémorisé, au lieu d'être relu
        // (et réécrit dans le cache) à chaque page.
        return cache()->remember('setting.v2:'.$key, 300, fn () => ['v' => static::where('key', $key)->first()?->value])['v'] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        cache()->forget('setting.v2:'.$key);
    }
}
