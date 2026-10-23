<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $items = [];
    private static bool $installed = false;

    public static function load(string $file): void
    {
        $defaults = require BASE_PATH . '/config/config.example.php';
        $custom = [];
        if (is_file($file)) {
            $custom = require $file;
            self::$installed = is_file(STORAGE_PATH . '/installed.lock');
        }
        self::$items = array_replace_recursive($defaults, is_array($custom) ? $custom : []);
    }

    public static function installed(): bool
    {
        return self::$installed;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $ref = &self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }
}
