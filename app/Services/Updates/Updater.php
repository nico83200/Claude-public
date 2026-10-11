<?php

namespace App\Services\Updates;

use App\Models\AppUpdate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Installation d'une nouvelle version depuis le zip de distribution.
 *
 * Étapes : vérification du paquet → maintenance → sauvegarde du code et de la base
 * → décompression → remplacement des fichiers (le .env et storage/ ne sont jamais
 * touchés) → migrations → remise en ligne. En cas d'échec pendant le remplacement
 * ou les migrations, le code précédent est restauré automatiquement.
 */
class Updater
{
    /** Le paquet contient un seul dossier racine. */
    public const ROOT = 'jackcie/';

    /** Fichiers qui doivent exister dans le paquet pour être reconnu comme une version de Jack&Cie. */
    public const REQUIRED = ['VERSION', 'artisan', 'composer.json', 'vendor/autoload.php', 'app/Support/Installation.php', 'public/index.php', 'bootstrap/app.php'];

    /** Chemins jamais remplacés ni sauvegardés (données et configuration du site). */
    public const PRESERVED = ['.env', 'storage/', 'public/storage', 'node_modules/', '.git/'];

    public const MAX_UNCOMPRESSED = 600 * 1024 * 1024;

    private const KEEP_BACKUPS = 3;

    public function __construct(private ?string $target = null, private ?string $workDir = null) {}

    public function target(): string
    {
        return rtrim($this->target ?? base_path(), '/');
    }

    public function workDir(): string
    {
        $dir = $this->workDir ?? storage_path('app/updates');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return rtrim($dir, '/');
    }

    public function currentVersion(): string
    {
        $file = $this->target().'/VERSION';

        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
    }

    // ------------------------------------------------------------------ Vérification

    /**
     * Contrôle le paquet sans rien installer.
     *
     * @return array{version: string, files: int, size: int}
     */
    public function inspect(string $zipPath): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('L\'extension PHP « zip » est nécessaire. Demandez son activation à votre hébergeur.');
        }
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Ce fichier n\'est pas une archive zip lisible.');
        }
        try {
            $size = 0;
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if (! $this->safeEntry($name)) {
                    throw new RuntimeException('Archive refusée : chemin non autorisé (« '.Str::limit($name, 80).' »).');
                }
                $size += $stat['size'];
                $names[$name] = true;
            }
            if ($size > self::MAX_UNCOMPRESSED) {
                throw new RuntimeException('Archive trop volumineuse une fois décompressée.');
            }
            foreach (self::REQUIRED as $required) {
                if (! isset($names[self::ROOT.$required])) {
                    throw new RuntimeException('Ce n\'est pas un paquet Jack&Cie complet (fichier manquant : '.$required.'). Utilisez le zip de distribution « jackcie-x.y.z.zip ».');
                }
            }
            $version = trim((string) $zip->getFromName(self::ROOT.'VERSION'));
            if (! preg_match('/^\d+\.\d+\.\d+([.-][0-9A-Za-z.-]+)?$/', $version)) {
                throw new RuntimeException('Numéro de version illisible dans le paquet.');
            }

            return ['version' => $version, 'files' => $zip->numFiles, 'size' => $size];
        } finally {
            $zip->close();
        }
    }

    /** Pas de chemin absolu, de remontée de dossier ni de fichier hors du dossier racine. */
    private function safeEntry(string $name): bool
    {
        if ($name === self::ROOT) {
            return true;
        }
        if (! str_starts_with($name, self::ROOT) || str_contains($name, '\\') || str_contains($name, "\0")) {
            return false;
        }
        foreach (explode('/', substr($name, strlen(self::ROOT))) as $part) {
            if ($part === '..' || $part === '.') {
                return false;
            }
        }

        return true;
    }

    /** Problèmes bloquants avant installation (droits d'écriture, place disque). */
    public function preflight(int $packageSize): array
    {
        $problems = [];
        foreach (['', 'app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor'] as $dir) {
            $path = $this->target().($dir ? '/'.$dir : '');
            if (is_dir($path) && ! is_writable($path)) {
                $problems[] = 'Dossier non modifiable par le serveur web : '.($dir ?: 'racine de l\'application').'.';
            }
        }
        $free = @disk_free_space($this->workDir());
        if ($free !== false && $free < $packageSize * 4) {
            $problems[] = 'Espace disque insuffisant (au moins '.round($packageSize * 4 / 1048576).' Mo libres nécessaires).';
        }

        return $problems;
    }

    // ------------------------------------------------------------------ Installation

    /**
     * Phase 1 : sauvegardes puis remplacement des fichiers. Le site reste en maintenance
     * jusqu'à la phase 2 (finalize), exécutée par une nouvelle requête qui charge le nouveau code.
     *
     * @param  array{backup_db?: bool, maintenance?: bool}  $options
     */
    public function apply(AppUpdate $update, array $options = []): void
    {
        $options += ['backup_db' => true, 'maintenance' => true];
        @set_time_limit(0);
        ignore_user_abort(true);

        $info = $this->inspect($update->package_path);
        $update->forceFill(['status' => 'applying', 'started_at' => now(), 'from_version' => $this->currentVersion(), 'to_version' => $info['version'], 'message' => null])->save();
        $stamp = now()->format('Ymd-His');
        $staging = $this->workDir().'/staging-'.$stamp;
        $copied = false;

        try {
            if ($options['maintenance']) {
                $update->forceFill(['recovery_secret' => Str::random(40)])->save();
                Artisan::call('down', ['--secret' => $update->recovery_secret, '--retry' => 60]);
                $update->appendLog('Site en maintenance.');
            }
            $update->forceFill(['backup_code_path' => $this->backupCode($this->workDir().'/sauvegarde-code-'.$update->from_version.'-'.$stamp.'.zip')])->save();
            $update->appendLog('Code actuel sauvegardé.');
            if ($options['backup_db']) {
                $update->forceFill(['backup_db_path' => $this->backupDatabase($this->workDir().'/sauvegarde-base-'.$stamp.'.sql.gz')])->save();
                $update->appendLog('Base de données sauvegardée.');
            }

            $zip = new ZipArchive;
            if ($zip->open($update->package_path) !== true || ! $zip->extractTo($staging)) {
                throw new RuntimeException('Décompression impossible.');
            }
            $zip->close();
            $update->appendLog('Paquet décompressé ('.$info['files'].' éléments).');

            $copied = true;
            $count = $this->copyTree($staging.'/'.rtrim(self::ROOT, '/'), $this->target());
            $this->clearBootstrapCache();
            $update->appendLog($count.' fichiers installés.');
            $update->forceFill(['status' => 'migrating'])->save();
        } catch (Throwable $e) {
            $this->fail($update, $e, $copied, $options['maintenance']);
        } finally {
            $this->deleteTree($staging);
        }
    }

    /** Phase 2 : migrations de la base et remise en ligne. */
    public function finalize(AppUpdate $update, bool $migrate = true): void
    {
        if ($update->status !== 'migrating') {
            return;
        }
        @set_time_limit(0);
        ignore_user_abort(true);
        try {
            if ($migrate) {
                Artisan::call('migrate', ['--force' => true]);
                $out = trim(preg_replace('/\s+/', ' ', Artisan::output()));
                $update->appendLog('Base de données : '.(! $out || str_contains($out, 'Nothing to migrate') ? 'déjà à jour.' : $out));
            }
            $this->clearCaches();
            $update->forceFill(['status' => 'done', 'finished_at' => now(), 'message' => 'Version '.$update->to_version.' installée.'])->save();
        } catch (Throwable $e) {
            $this->fail($update, $e, true, false);
        } finally {
            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
            }
            $this->pruneBackups();
        }
    }

    private function fail(AppUpdate $update, Throwable $e, bool $copied, bool $bringUp): never
    {
        report($e);
        $update->appendLog('ERREUR : '.$e->getMessage());
        if ($copied && $update->backup_code_path) {
            try {
                $this->restoreCode($update->backup_code_path);
                $this->clearBootstrapCache();
                $update->appendLog('Code de la version '.$update->from_version.' restauré.');
            } catch (Throwable $restoreError) {
                $update->appendLog('Restauration automatique impossible : '.$restoreError->getMessage());
            }
        }
        $update->forceFill(['status' => 'failed', 'finished_at' => now(), 'message' => 'Échec : '.$e->getMessage()])->save();
        if ($bringUp && app()->isDownForMaintenance()) {
            Artisan::call('up');
        }
        throw new RuntimeException('La mise à jour a échoué : '.$e->getMessage().($copied ? ' Le code de la version précédente a été restauré'.($update->backup_db_path ? ' ; si nécessaire, la sauvegarde de la base est disponible.' : '.') : ''), 0, $e);
    }

    /** Remet le code sauvegardé avant une mise à jour (la base n'est pas modifiée). */
    public function restore(AppUpdate $update): void
    {
        if (! $update->backup_code_path || ! is_file($update->backup_code_path)) {
            throw new RuntimeException('La sauvegarde du code n\'est plus disponible.');
        }
        @set_time_limit(0);
        $this->restoreCode($update->backup_code_path);
        $this->clearBootstrapCache();
        $this->clearCaches();
        $update->appendLog('Code de la version '.$update->from_version.' restauré manuellement.');
        $update->forceFill(['status' => 'restored', 'message' => 'Version '.$update->from_version.' restaurée. Si la base avait été mise à jour, restaurez aussi la sauvegarde de la base.'])->save();
    }

    // ------------------------------------------------------------------ Fichiers

    private function preserved(string $relative): bool
    {
        foreach (self::PRESERVED as $p) {
            if ($relative === rtrim($p, '/') || (str_ends_with($p, '/') && str_starts_with($relative, $p))) {
                return true;
            }
        }

        return false;
    }

    /** Copie fichier par fichier (écriture dans un fichier temporaire puis renommage). */
    private function copyTree(string $from, string $to): int
    {
        if (! is_dir($from)) {
            throw new RuntimeException('Contenu du paquet introuvable.');
        }
        $count = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $file) {
            $relative = substr($file->getPathname(), strlen($from) + 1);
            if ($this->preserved($relative)) {
                continue;
            }
            $dest = $to.'/'.$relative;
            if ($file->isDir()) {
                if (! is_dir($dest) && ! @mkdir($dest, 0755, true) && ! is_dir($dest)) {
                    throw new RuntimeException('Impossible de créer le dossier '.$relative.'.');
                }

                continue;
            }
            if (is_dir($dest)) {
                throw new RuntimeException('Conflit : '.$relative.' est un dossier sur le serveur.');
            }
            $tmp = $dest.'.jc-tmp';
            if (! @copy($file->getPathname(), $tmp) || ! @rename($tmp, $dest)) {
                @unlink($tmp);
                throw new RuntimeException('Écriture impossible : '.$relative.'.');
            }
            @chmod($dest, $file->getPerms() & 0777);
            $count++;
        }

        return $count;
    }

    private function backupCode(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer la sauvegarde du code.');
        }
        $root = $this->target();
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if ($file->isFile() && ! $this->preserved($relative) && ! str_starts_with($relative, 'bootstrap/cache/') && ! str_starts_with($relative, 'dist/')) {
                $zip->addFile($file->getPathname(), self::ROOT.$relative);
                $zip->setCompressionName(self::ROOT.$relative, ZipArchive::CM_STORE);
            }
        }
        if (! $zip->close()) {
            throw new RuntimeException('Échec de l\'écriture de la sauvegarde du code.');
        }

        return $path;
    }

    private function restoreCode(string $backup): void
    {
        $dir = $this->workDir().'/restore-'.Str::random(6);
        $zip = new ZipArchive;
        if ($zip->open($backup) !== true || ! $zip->extractTo($dir)) {
            throw new RuntimeException('Sauvegarde illisible.');
        }
        $zip->close();
        try {
            $this->copyTree($dir.'/'.rtrim(self::ROOT, '/'), $this->target());
        } finally {
            $this->deleteTree($dir);
        }
    }

    /** Sauvegarde SQL complète (structure + données), compressée, sans outil externe. */
    public function backupDatabase(string $path): string
    {
        $gz = gzopen($path, 'wb6');
        if (! $gz) {
            throw new RuntimeException('Impossible de créer la sauvegarde de la base.');
        }
        $pdo = DB::connection()->getPdo();
        gzwrite($gz, '-- Sauvegarde Jack&Cie '.now()->toIso8601String()."\nSET FOREIGN_KEY_CHECKS=0;\n");
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $table = array_values((array) $row)[0];
            $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
            gzwrite($gz, "\nDROP TABLE IF EXISTS `$table`;\n".array_values($create)[1].";\n");
            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($gz, $pdo, $table) {
                $values = [];
                foreach ($rows as $r) {
                    $values[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $r)).')';
                }
                if ($values) {
                    gzwrite($gz, 'INSERT INTO `'.$table.'` VALUES '.implode(",\n", $values).";\n");
                }
            });
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        return $path;
    }

    private function clearBootstrapCache(): void
    {
        foreach (glob($this->target().'/bootstrap/cache/*.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function clearCaches(): void
    {
        if ($this->target() === rtrim(base_path(), '/')) {
            rescue(fn () => Artisan::call('optimize:clear'), null, false);
        }
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && ! $f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /** Ne garde que les dernières sauvegardes et supprime les paquets déjà installés. */
    private function pruneBackups(): void
    {
        foreach (['sauvegarde-code-*.zip', 'sauvegarde-base-*.sql.gz'] as $pattern) {
            $files = glob($this->workDir().'/'.$pattern) ?: [];
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($files, self::KEEP_BACKUPS) as $old) {
                @unlink($old);
            }
        }
    }
}
