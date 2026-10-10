<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['value' => 'array'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return cache()->remember('setting:'.$key, 300, fn () => static::where('key', $key)->first()?->value) ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        cache()->forget('setting:'.$key);
    }
}
