<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Lecture / écriture contrôlée du fichier .env (installeur uniquement).
 */
class EnvEditor
{
    public function path(): string
    {
        return app()->environmentFilePath();
    }

    /** Crée le .env à partir de .env.example si nécessaire. */
    public function ensureExists(): void
    {
        if (! File::exists($this->path())) {
            $example = base_path('.env.example');
            File::put($this->path(), File::exists($example) ? File::get($example) : "APP_NAME=\"Jack&Cie\"\nAPP_KEY=\n");
        }
    }

    public function get(string $key): ?string
    {
        if (! File::exists($this->path())) {
            return null;
        }
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', File::get($this->path()), $m)) {
            return trim($m[1], " \t\"'");
        }

        return null;
    }

    /** @param array<string, string|int|bool|null> $values */
    public function set(array $values): void
    {
        $this->ensureExists();
        $content = File::get($this->path());
        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content)
                : rtrim($content)."\n".$line."\n";
        }
        File::put($this->path(), $content);
    }

    public function writable(): bool
    {
        return File::exists($this->path()) ? is_writable($this->path()) : is_writable(dirname($this->path()));
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    private function format(string|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\s#"\'\\\\$&=;`]/', $value)) {
            // Guillemets simples : valeur littérale pour phpdotenv (ni échappement ni interpolation).
            if (! str_contains($value, "'")) {
                return "'".$value."'";
            }
            if (str_contains($value, '${')) {
                throw new \InvalidArgumentException('Valeur non prise en charge dans le fichier .env.');
            }

            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return Str::of($value)->replace("\n", '')->toString();
    }
}
