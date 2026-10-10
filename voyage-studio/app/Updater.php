<?php
declare(strict_types=1);

/**
 * Mise à jour de l'application par dépôt d'un ZIP (Réglages > Mise à jour).
 *
 * Sécurité / robustesse :
 *  - le paquet doit contenir VERSION + les fichiers cœur (sinon refusé) ;
 *  - seuls les fichiers applicatifs connus sont écrits (liste blanche), jamais config.php ni data/ ;
 *  - chemins validés (pas de « .. », pas de chemins absolus) contre le zip-slip ;
 *  - sauvegarde automatique des fichiers actuels ET de la base avant installation ;
 *  - extraction en zone tampon puis copie : une archive corrompue n'altère pas l'installation.
 */
final class Updater
{
    private const ALLOWED_DIRS = ['app/', 'assets/', 'vendor/', 'scripts/', 'tests/'];
    private const ALLOWED_FILES = ['index.php', 'api.php', 'install.php', '.htaccess', 'VERSION', 'README.md',
        'composer.json', 'composer.lock', 'config.sample.php', '.gitignore'];
    private const REQUIRED = ['VERSION', 'index.php', 'api.php', 'app/bootstrap.php', 'app/Schema.php', 'assets/js/app.js'];
    /** Dossiers dont les fichiers obsolètes sont supprimés après mise à jour. */
    private const CLEAN_DIRS = ['app/', 'assets/', 'vendor/'];
    private const KEEP_BACKUPS = 5;

    private string $root;

    public function __construct(string $root, private Database $db)
    {
        $this->root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
    }

    private function backupDir(): string
    {
        return $this->root . '/data/backups';
    }

    public function status(): array
    {
        $backups = [];
        foreach (glob($this->backupDir() . '/*.zip') ?: [] as $f) {
            $backups[] = ['file' => basename($f), 'size' => filesize($f), 'date' => date('Y-m-d H:i', filemtime($f))];
        }
        usort($backups, fn($a, $b) => strcmp($b['file'], $a['file']));
        return [
            'version' => VS_VERSION,
            'php' => PHP_VERSION,
            'zip' => class_exists('ZipArchive'),
            'writable' => is_writable($this->root) && is_writable($this->root . '/app') && is_writable($this->root . '/assets'),
            'max_upload' => min(self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size')),
            'backups' => $backups,
        ];
    }

    public static function iniBytes(string $key): int
    {
        $v = trim((string)ini_get($key));
        if ($v === '' || $v === '-1' || $v === '0') {
            return PHP_INT_MAX;
        }
        $n = (int)$v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
        };
    }

    /** Analyse un paquet sans l'installer. */
    public function inspect(string $zipPath): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("L'extension PHP zip est absente de votre hébergement.");
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Archive ZIP illisible.');
        }
        try {
            // Le paquet peut contenir un dossier racine unique (ex. voyagestudio/…)
            $prefix = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if (preg_match('#^([^/]+/)?VERSION$#', $name, $m)) {
                    $prefix = $m[1] ?? '';
                    break;
                }
            }
            if ($prefix === null) {
                throw new RuntimeException("Ce ZIP n'est pas un paquet VoyageStudio (fichier VERSION absent).");
            }
            $files = [];
            $ignored = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if (!str_starts_with($name, $prefix) || str_ends_with($name, '/')) {
                    continue;
                }
                $rel = substr($name, strlen($prefix));
                if (!self::safePath($rel)) {
                    throw new RuntimeException("Chemin dangereux dans l'archive : $rel");
                }
                if (self::allowed($rel)) {
                    $files[$rel] = $i;
                } else {
                    $ignored[] = $rel;
                }
            }
            foreach (self::REQUIRED as $req) {
                if (!isset($files[$req])) {
                    throw new RuntimeException("Paquet incomplet : $req manquant.");
                }
            }
            $version = trim((string)$zip->getFromIndex($files['VERSION']));
            if (!preg_match('/^\d+\.\d+\.\d+([.-][0-9A-Za-z.-]+)?$/', $version)) {
                throw new RuntimeException('Numéro de version invalide dans le paquet.');
            }
            return ['version' => $version, 'current' => VS_VERSION, 'files' => count($files),
                'ignored' => array_slice($ignored, 0, 20), 'prefix' => $prefix, '_map' => $files,
                'vendor' => (bool)array_filter(array_keys($files), fn($f) => str_starts_with($f, 'vendor/'))];
        } finally {
            $zip->close();
        }
    }

    public function install(string $zipPath, bool $allowDowngrade = false): array
    {
        $info = $this->inspect($zipPath);
        if (version_compare($info['version'], VS_VERSION, '<') && !$allowDowngrade) {
            throw new RuntimeException("Le paquet ({$info['version']}) est plus ancien que la version installée (" . VS_VERSION . '). Cochez « autoriser le retour arrière » pour forcer.');
        }
        if (!$this->status()['writable']) {
            throw new RuntimeException("Droits d'écriture insuffisants sur les fichiers de l'application (vérifiez via votre FTP).");
        }
        @set_time_limit(300);

        // 1. Sauvegardes
        $backup = $this->backup();

        // 2. Extraction en zone tampon
        $staging = $this->root . '/data/updates/staging-' . bin2hex(random_bytes(4));
        if (!@mkdir($staging, 0755, true)) {
            throw new RuntimeException('Impossible de créer le dossier temporaire dans data/.');
        }
        $zip = new ZipArchive();
        $zip->open($zipPath);
        try {
            foreach ($info['_map'] as $rel => $idx) {
                $dest = $staging . '/' . $rel;
                if (!is_dir(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                $stream = $zip->getStream((string)$zip->getNameIndex($idx));
                if (!$stream || file_put_contents($dest, $stream) === false) {
                    throw new RuntimeException("Extraction impossible : $rel");
                }
                fclose($stream);
            }
        } catch (Throwable $e) {
            $zip->close();
            self::rrmdir($staging);
            throw $e;
        }
        $zip->close();

        // 3. Copie en place (fichier par fichier, écriture atomique via rename)
        $written = 0;
        foreach (array_keys($info['_map']) as $rel) {
            $target = $this->root . '/' . $rel;
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            $tmp = $target . '.vs-new';
            if (!copy($staging . '/' . $rel, $tmp) || !rename($tmp, $target)) {
                @unlink($tmp);
                throw new RuntimeException("Écriture impossible : $rel — restaurez la sauvegarde {$backup['files']} si l'application ne répond plus.");
            }
            $written++;
        }

        // 4. Nettoyage des fichiers obsolètes (code uniquement)
        $removed = 0;
        $newSet = array_flip(array_keys($info['_map']));
        $hasVendor = $info['vendor'];
        foreach (self::CLEAN_DIRS as $dir) {
            if ($dir === 'vendor/' && !$hasVendor) {
                continue; // paquet « léger » sans vendor : on conserve l'existant
            }
            foreach (self::listFiles($this->root . '/' . rtrim($dir, '/')) as $abs) {
                $rel = substr($abs, strlen($this->root) + 1);
                if (!isset($newSet[$rel]) && basename($rel) !== '.htaccess') {
                    @unlink($abs);
                    $removed++;
                }
            }
        }
        self::rrmdir($staging);
        foreach (glob($this->root . '/data/.schema-*') ?: [] as $m) {
            @unlink($m); // la migration du schéma s'exécutera à la prochaine requête
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return ['from' => VS_VERSION, 'to' => $info['version'], 'written' => $written, 'removed' => $removed, 'backup' => $backup];
    }

    /** Sauvegarde des fichiers applicatifs (ZIP) + export JSON de la base. */
    public function backup(): array
    {
        $dir = $this->backupDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new RuntimeException('Impossible de créer data/backups.');
        }
        $stamp = date('Ymd-His') . '-v' . VS_VERSION;
        $zipFile = "$dir/app-$stamp.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer la sauvegarde.');
        }
        foreach (self::ALLOWED_FILES as $f) {
            if (is_file($this->root . '/' . $f)) {
                $zip->addFile($this->root . '/' . $f, $f);
            }
        }
        foreach (self::ALLOWED_DIRS as $d) {
            foreach (self::listFiles($this->root . '/' . rtrim($d, '/')) as $abs) {
                $zip->addFile($abs, substr($abs, strlen($this->root) + 1));
            }
        }
        $zip->close();

        $dump = ['app' => 'VoyageStudio', 'version' => 1, 'date' => date('c'), 'tables' => []];
        $repo = new Repository($this->db);
        foreach ([...array_keys(Schema::entities()), 'settings'] as $t) {
            $dump['tables'][$t] = $this->db->all('SELECT * FROM ' . $repo->q($t));
        }
        $dbFile = "$dir/base-$stamp.json";
        file_put_contents($dbFile, json_encode($dump, JSON_UNESCAPED_UNICODE));

        // Rotation
        foreach (['app-*.zip', 'base-*.json'] as $pattern) {
            $list = glob("$dir/$pattern") ?: [];
            rsort($list);
            foreach (array_slice($list, self::KEEP_BACKUPS) as $old) {
                @unlink($old);
            }
        }
        return ['files' => basename($zipFile), 'database' => basename($dbFile)];
    }

    public function backupPath(string $file): string
    {
        if (!preg_match('/^(app|base)-[0-9]{8}-[0-9]{6}-v[0-9A-Za-z.-]+\.(zip|json)$/', $file) || !is_file($this->backupDir() . '/' . $file)) {
            throw new NotFoundException('Sauvegarde introuvable');
        }
        return $this->backupDir() . '/' . $file;
    }

    public static function safePath(string $rel): bool
    {
        return $rel !== '' && !str_contains($rel, "\0") && !str_contains($rel, '\\') && !str_starts_with($rel, '/')
            && !preg_match('#(^|/)\.\.(/|$)#', $rel) && !preg_match('#^[A-Za-z]:#', $rel);
    }

    public static function allowed(string $rel): bool
    {
        if ($rel === 'config.php' || str_starts_with($rel, 'data/')) {
            return false;
        }
        if (in_array($rel, self::ALLOWED_FILES, true)) {
            return true;
        }
        foreach (self::ALLOWED_DIRS as $d) {
            if (str_starts_with($rel, $d)) {
                return true;
            }
        }
        return false;
    }

    private static function listFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $out[] = str_replace('\\', '/', $f->getPathname());
            }
        }
        return $out;
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
