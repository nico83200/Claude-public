<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * État d'installation de l'application.
 *
 * L'installation est terminée quand le fichier verrou `storage/app/installed.json`
 * existe. Tant que ce n'est pas le cas, toutes les pages redirigent vers
 * l'installeur web (/install) ; ensuite l'installeur renvoie une 404.
 * APP_INSTALLED=true permet de court-circuiter cette vérification
 * (installation en ligne de commande, tests automatisés).
 */
class Installation
{
    public static function lockPath(): string
    {
        return storage_path('app/installed.json');
    }

    public static function isInstalled(): bool
    {
        $override = config('equine.installed');
        if ($override !== null && $override !== '') {
            return filter_var($override, FILTER_VALIDATE_BOOLEAN);
        }

        return File::exists(static::lockPath());
    }

    public static function markInstalled(array $meta = []): void
    {
        File::ensureDirectoryExists(dirname(static::lockPath()));
        File::put(static::lockPath(), json_encode(['installed_at' => now()->toIso8601String(), 'version' => config('app.version', '1.0')] + $meta, JSON_PRETTY_PRINT));
    }

    /** Vérification de l'environnement serveur. */
    public static function requirements(): array
    {
        $checks = [];
        $checks[] = ['PHP '.PHP_VERSION.' (8.3 minimum)', version_compare(PHP_VERSION, '8.3.0', '>='), true];
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'gd', 'curl', 'zip'] as $ext) {
            $checks[] = ['Extension '.$ext, extension_loaded($ext), true];
        }
        foreach (['intl' => 'dates localisées', 'exif' => 'orientation des photos', 'bcmath' => 'calculs'] as $ext => $why) {
            $checks[] = ['Extension '.$ext.' (recommandée : '.$why.')', extension_loaded($ext), false];
        }
        foreach ([storage_path(), storage_path('app'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
            $checks[] = ['Dossier accessible en écriture : '.str_replace(base_path().'/', '', $dir), is_dir($dir) && is_writable($dir), true];
        }
        $env = app()->environmentFilePath();
        $checks[] = ['Fichier .env modifiable', File::exists($env) ? is_writable($env) : is_writable(dirname($env)), false];
        $checks[] = ['Ressources compilées (public/build)', File::exists(public_path('build/manifest.json')), true];

        return array_map(fn ($c) => ['label' => $c[0], 'ok' => (bool) $c[1], 'required' => $c[2]], $checks);
    }

    public static function requirementsMet(): bool
    {
        return collect(static::requirements())->every(fn ($c) => $c['ok'] || ! $c['required']);
    }
}
