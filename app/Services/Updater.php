<?php
declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/**
 * Mise à jour du site à partir d'un ZIP de nouvelle version, depuis l'administration.
 *
 * Étapes : lecture et contrôle du paquet (structure, chemins, version, syntaxe PHP) → sauvegarde
 * des fichiers qui vont être remplacés → mode maintenance → copie → vidage d'OPcache.
 * Les migrations de base s'exécutent ensuite dans une nouvelle requête (avec le nouveau code).
 * La configuration, les données (storage/) et les téléversements ne sont jamais touchés.
 */
final class Updater
{
    /** Chemins jamais écrasés par une mise à jour. */
    private const PROTECTED = ['config/config.php', 'storage/', 'public/uploads/'];
    /** Exceptions dans les chemins protégés : fichiers livrés avec l'application. */
    private const SHIPPED = ['storage/fonts/', 'storage/.htaccess', 'public/uploads/.htaccess'];
    private const REQUIRED = ['VERSION', 'app/bootstrap.php', 'public/index.php', 'database/schema.sql'];
    private const MAX_FILES = 3000;
    private const MAX_BYTES = 150 * 1024 * 1024;
    private const KEEP_BACKUPS = 5;
    private const MAINTENANCE_MAX_AGE = 600;

    public function __construct(private readonly string $root = BASE_PATH)
    {
    }

    public function currentVersion(): string
    {
        return trim((string) @file_get_contents($this->root . '/VERSION')) ?: '0.0.0';
    }

    public function backupDir(): string
    {
        return $this->root . '/storage/backups';
    }

    public function maintenanceFile(): string
    {
        return $this->root . '/storage/maintenance.flag';
    }

    /** Vrai pendant la copie des fichiers (ignoré au-delà de 10 min, en cas d'interruption). */
    public function inMaintenance(): bool
    {
        $file = $this->maintenanceFile();
        return is_file($file) && filemtime($file) > time() - self::MAINTENANCE_MAX_AGE;
    }

    /**
     * Analyse le paquet sans rien modifier.
     * @return array{version:string, files:array<string,int>, zip:ZipArchive}
     */
    public function inspect(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('L\'extension PHP « zip » est requise (hPanel › Configuration PHP › Extensions).');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Fichier ZIP illisible ou corrompu.');
        }
        if ($zip->numFiles > self::MAX_FILES) {
            throw new \RuntimeException('Le ZIP contient trop de fichiers (' . $zip->numFiles . ').');
        }

        // Le ZIP peut contenir les fichiers à la racine ou dans un dossier unique (ex. ZIP GitHub « depot-main/ »).
        $prefix = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === 'app/bootstrap.php' || preg_match('#^([^/]+/)app/bootstrap\.php$#', $name)) {
                $prefix = $name === 'app/bootstrap.php' ? '' : substr($name, 0, -strlen('app/bootstrap.php'));
                break;
            }
        }
        if ($prefix === null) {
            throw new \RuntimeException('Ce ZIP n\'est pas une version de la boutique (app/bootstrap.php introuvable).');
        }

        $files = [];
        $bytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) $stat['name'];
            if (!self::safePath(rtrim($name, '/'))) {
                throw new \RuntimeException('Chemin refusé dans le ZIP : ' . mb_substr($name, 0, 120));
            }
            if (str_ends_with($name, '/')) {
                continue; // dossier
            }
            if ($prefix !== '' && !str_starts_with($name, $prefix)) {
                continue;
            }
            $rel = substr($name, strlen($prefix));
            if (!self::safePath($rel)) {
                throw new \RuntimeException('Chemin refusé dans le ZIP : ' . $rel);
            }
            if (str_starts_with($rel, '.git/') || str_contains($rel, '/.git/') || $this->isProtected($rel)) {
                continue;
            }
            $bytes += (int) $stat['size'];
            $files[$rel] = $i;
        }
        if ($bytes > self::MAX_BYTES) {
            throw new \RuntimeException('Le contenu décompressé dépasse ' . (self::MAX_BYTES >> 20) . ' Mo.');
        }
        foreach (self::REQUIRED as $required) {
            if (!isset($files[$required])) {
                throw new \RuntimeException("Fichier obligatoire absent du ZIP : $required");
            }
        }

        $version = trim((string) $zip->getFromIndex($files['VERSION']));
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new \RuntimeException('Numéro de version invalide dans le fichier VERSION : « ' . mb_substr($version, 0, 20) . ' ».');
        }

        // Contrôle de syntaxe de chaque fichier PHP : un fichier cassé rendrait le site inaccessible.
        foreach ($files as $rel => $index) {
            if (str_ends_with($rel, '.php')) {
                try {
                    token_get_all((string) $zip->getFromIndex($index), TOKEN_PARSE);
                } catch (\ParseError $e) {
                    throw new \RuntimeException("Erreur de syntaxe dans $rel (ligne {$e->getLine()}) : {$e->getMessage()}");
                }
            }
        }

        return ['version' => $version, 'files' => $files, 'zip' => $zip];
    }

    /**
     * Applique le paquet. Retourne un résumé ; lève une exception (sans rien modifier) si le paquet est refusé.
     */
    public function install(string $zipPath, bool $allowDowngrade = false): array
    {
        $package = $this->inspect($zipPath);
        $from = $this->currentVersion();
        if (!$allowDowngrade && version_compare($package['version'], $from, '<')) {
            throw new \RuntimeException("Ce ZIP contient la version {$package['version']}, plus ancienne que la version installée ($from). Utilisez plutôt une sauvegarde pour revenir en arrière.");
        }

        $backup = $this->backup(array_keys($package['files']), $from);
        $written = $this->write($package['zip'], $package['files']);
        $package['zip']->close();

        return ['from' => $from, 'to' => $package['version'], 'files' => $written, 'backup' => $backup];
    }

    /** Remet en place les fichiers d'une sauvegarde (les migrations de base ne sont pas annulées). */
    public function restore(string $backupName): array
    {
        $path = $this->backupDir() . '/' . basename($backupName);
        if (!preg_match('/^avant-[\w.\-]+\.zip$/', basename($backupName)) || !is_file($path)) {
            throw new \RuntimeException('Sauvegarde introuvable.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Sauvegarde illisible.');
        }
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $rel = (string) $zip->getNameIndex($i);
            if (!str_ends_with($rel, '/') && self::safePath($rel) && !$this->isProtected($rel)) {
                $files[$rel] = $i;
            }
        }
        $current = $this->currentVersion();
        $safety = $this->backup(array_keys($files), $current);
        $written = $this->write($zip, $files);
        $zip->close();
        return ['files' => $written, 'version' => $this->currentVersion(), 'backup' => $safety];
    }

    /** @return list<array{name:string, size:int, date:int}> */
    public function backups(): array
    {
        $list = [];
        foreach (glob($this->backupDir() . '/avant-*.zip') ?: [] as $file) {
            $list[] = ['name' => basename($file), 'size' => (int) filesize($file), 'date' => (int) filemtime($file)];
        }
        usort($list, static fn ($a, $b) => $b['date'] <=> $a['date']);
        return $list;
    }

    private function backup(array $relPaths, string $version): string
    {
        $dir = $this->backupDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Impossible de créer le dossier de sauvegarde.');
        }
        $name = 'avant-' . date('Ymd-His') . '-v' . preg_replace('/[^\d.]/', '', $version) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($dir . '/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer la sauvegarde.');
        }
        foreach (array_unique([...$relPaths, 'VERSION']) as $rel) {
            $file = $this->root . '/' . $rel;
            if (is_file($file)) {
                $zip->addFile($file, $rel);
            }
        }
        if (!$zip->close()) {
            throw new \RuntimeException('Échec de l\'écriture de la sauvegarde (espace disque ?).');
        }

        foreach (array_slice($this->backups(), self::KEEP_BACKUPS) as $old) {
            @unlink($dir . '/' . $old['name']);
        }
        return $name;
    }

    /** Copie les fichiers (écriture dans un fichier temporaire puis renommage) en mode maintenance. */
    private function write(ZipArchive $zip, array $files): int
    {
        touch($this->maintenanceFile());
        $count = 0;
        try {
            foreach ($files as $rel => $index) {
                $target = $this->root . '/' . $rel;
                $dir = dirname($target);
                if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Impossible de créer le dossier $rel");
                }
                $content = $zip->getFromIndex($index);
                if ($content === false) {
                    throw new \RuntimeException("Lecture impossible dans le ZIP : $rel");
                }
                $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
                if (file_put_contents($tmp, $content) === false || !rename($tmp, $target)) {
                    @unlink($tmp);
                    throw new \RuntimeException("Écriture impossible : $rel (droits ou espace disque)");
                }
                $count++;
            }
        } finally {
            @unlink($this->maintenanceFile());
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
        }
        return $count;
    }

    private function isProtected(string $rel): bool
    {
        foreach (self::SHIPPED as $allowed) {
            if (str_starts_with($rel, $allowed)) {
                return false;
            }
        }
        foreach (self::PROTECTED as $protected) {
            if ($rel === $protected || (str_ends_with($protected, '/') && str_starts_with($rel, $protected))) {
                return true;
            }
        }
        return false;
    }

    /** Refuse les chemins absolus, les remontées (../) et les caractères ambigus (protection « zip slip »). */
    private static function safePath(string $rel): bool
    {
        if ($rel === '' || $rel[0] === '/' || str_contains($rel, '\\') || str_contains($rel, ':') || str_contains($rel, "\0")) {
            return false;
        }
        foreach (explode('/', $rel) as $segment) {
            if ($segment === '..' || $segment === '.' || $segment === '') {
                return false;
            }
        }
        return true;
    }
}
