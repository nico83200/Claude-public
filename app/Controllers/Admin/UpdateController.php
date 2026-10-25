<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Migrator;
use App\Services\DbBackup;
use App\Services\Updater;
use App\Services\Upload;

final class UpdateController extends BaseController
{
    public function index(): void
    {
        $updater = new Updater();
        $pending = [];
        $migrationError = null;
        try {
            $pending = Migrator::pending();
        } catch (\Throwable $e) {
            $migrationError = $e->getMessage();
        }
        $this->render('update', [
            'pageTitle'      => 'Mise à jour du site',
            'version'        => $updater->currentVersion(),
            'backups'        => $updater->backups(),
            'dbBackups'      => DbBackup::list(),
            'pending'        => $pending,
            'migrationError' => $migrationError,
            'zipAvailable'   => class_exists(\ZipArchive::class),
            'maxUpload'      => ini_get('upload_max_filesize'),
            'serverPackages' => array_keys(self::serverPackages()),
            'diagnostics'    => self::diagnostics(),
        ]);
    }

    public function upload(): void
    {
        $file = Upload::files('package')[0] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash('error', $file ? 'Échec du téléversement (code ' . $file['error'] . ') : fichier trop volumineux ?' : 'Choisissez le fichier ZIP de la nouvelle version.');
            redirect('admin/mise-a-jour');
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
            flash('error', 'Le fichier doit être une archive .zip.');
            redirect('admin/mise-a-jour');
        }

        // Le ZIP reçu est déplacé dans un dossier de travail ; si aucun n'est accessible, il est lu
        // directement depuis le dossier temporaire de PHP (il y reste jusqu'à la fin de la requête).
        $tmp = null;
        $own = true;
        foreach ([STORAGE_PATH . '/cache', STORAGE_PATH . '/updates', sys_get_temp_dir()] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $target = $dir . '/update-' . bin2hex(random_bytes(6)) . '.zip';
            if (is_dir($dir) && is_writable($dir) && @move_uploaded_file($file['tmp_name'], $target)) {
                $tmp = $target;
                break;
            }
        }
        if ($tmp === null && is_uploaded_file($file['tmp_name']) && is_readable($file['tmp_name'])) {
            $tmp = $file['tmp_name'];
            $own = false;
        }
        if ($tmp === null) {
            $diag = self::diagnostics();
            log_message('update', 'Fichier téléversé impossible à enregistrer', $diag + ['php' => error_get_last()['message'] ?? null]);
            flash('error', 'Impossible d\'enregistrer le fichier téléversé : ' . self::diagnosis($diag)
                . ' Vous pouvez aussi déposer le ZIP dans le dossier storage/updates avec le gestionnaire de fichiers Hostinger, puis l\'installer ci-dessous.');
            redirect('admin/mise-a-jour');
        }

        $this->installPackage($tmp, (string) $file['name'], $own);
    }

    /** Installe un ZIP déposé sur le serveur (gestionnaire de fichiers) : contourne les limites d'envoi. */
    public function installFromServer(string $name): void
    {
        $path = self::serverPackages()[$name] ?? abort(404);
        $this->installPackage($path, $name, true);
    }

    /** ZIP de mise à jour déposés dans storage/updates ou à la racine du site, par nom. */
    public static function serverPackages(): array
    {
        $found = [];
        foreach ([STORAGE_PATH . '/updates/*.zip', BASE_PATH . '/criniere-club-*.zip'] as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                if (!str_starts_with(basename($path), 'update-')) {
                    $found[basename($path)] = $path;
                }
            }
        }
        ksort($found);
        return $found;
    }

    /** État du serveur utile pour comprendre un échec d'envoi. */
    public static function diagnostics(): array
    {
        $free = @disk_free_space(STORAGE_PATH);
        return [
            'cache_exists'   => is_dir(STORAGE_PATH . '/cache'),
            'cache_writable' => is_writable(STORAGE_PATH . '/cache'),
            'storage_writable' => is_writable(STORAGE_PATH),
            'tmp_dir'        => sys_get_temp_dir(),
            'tmp_writable'   => is_writable(sys_get_temp_dir()),
            'free_mb'        => $free === false ? null : (int) floor($free / 1048576),
            'upload_max'     => ini_get('upload_max_filesize'),
            'post_max'       => ini_get('post_max_size'),
            'zip'            => class_exists(\ZipArchive::class),
        ];
    }

    private static function diagnosis(array $d): string
    {
        if ($d['free_mb'] !== null && $d['free_mb'] < 50) {
            return 'espace disque presque plein (' . $d['free_mb'] . ' Mo libres) : supprimez d\'anciennes sauvegardes (storage/backups) ou libérez de la place dans hPanel.';
        }
        if (!$d['cache_exists']) {
            return 'le dossier storage/cache n\'existe pas et n\'a pas pu être créé.';
        }
        if (!$d['cache_writable'] || !$d['storage_writable']) {
            return 'le dossier storage (ou storage/cache) n\'est pas accessible en écriture : dans le gestionnaire de fichiers, donnez-lui les droits 755.';
        }
        return 'le serveur a refusé la copie (voir le journal storage/logs/update-' . date('Y-m') . '.log).';
    }

    private function installPackage(string $tmp, string $name, bool $deleteAfter): void
    {
        // Copie de la base de données avant toute modification (les migrations peuvent la changer)
        $dbBackup = null;
        try {
            $dbBackup = DbBackup::create('avant-mise-a-jour');
        } catch (\Throwable $e) {
            log_message('update', 'Sauvegarde de la base impossible', ['error' => $e->getMessage()]);
        }

        try {
            $result = (new Updater())->install($tmp, input('downgrade') === '1');
            $result['db_backup'] = $dbBackup;
        } catch (\Throwable $e) {
            log_message('update', 'Mise à jour refusée', ['file' => $name, 'error' => $e->getMessage(), 'admin' => $this->admin['email']]);
            flash('error', 'Mise à jour annulée, aucun fichier n\'a été modifié : ' . $e->getMessage());
            redirect('admin/mise-a-jour');
        } finally {
            if ($deleteAfter) {
                @unlink($tmp);
            }
        }

        log_message('update', 'Mise à jour installée', $result + ['admin' => $this->admin['email']]);
        $_SESSION['update_result'] = $result;
        // Les migrations s'exécutent dans une nouvelle requête, avec le nouveau code chargé.
        redirect('admin/mise-a-jour/finaliser');
    }

    public function finalize(): void
    {
        $result = $_SESSION['update_result'] ?? null;
        unset($_SESSION['update_result']);
        $message = $result
            ? "Version {$result['to']} installée (précédente : {$result['from']}) — {$result['files']} fichier(s) mis à jour, sauvegarde {$result['backup']}"
                . (!empty($result['db_backup']) ? ", base de données sauvegardée ({$result['db_backup']})." : ' (base de données NON sauvegardée : voir le journal).')
            : 'Finalisation effectuée.';
        try {
            $applied = Migrator::run();
            flash('success', $message . ($applied ? ' Migrations appliquées : ' . implode(', ', $applied) . '.' : ' Base de données à jour.'));
        } catch (\Throwable $e) {
            log_message('update', 'Échec des migrations', ['error' => $e->getMessage()]);
            flash('error', $message . ' ATTENTION, la migration de la base a échoué : ' . $e->getMessage() . ' — Corrigez puis cliquez sur « Appliquer les migrations », ou restaurez la sauvegarde.');
        }
        redirect('admin/mise-a-jour');
    }

    public function backupDatabase(): void
    {
        try {
            $name = DbBackup::create('manuelle');
            flash('success', 'Base de données sauvegardée : ' . $name . '. Vous pouvez la télécharger ci-dessous.');
        } catch (\Throwable $e) {
            flash('error', 'Sauvegarde impossible : ' . $e->getMessage());
        }
        redirect('admin/mise-a-jour#base');
    }

    public function downloadDatabase(string $name): void
    {
        $path = DbBackup::path($name) ?? abort(404);
        log_message('update', 'Sauvegarde de la base téléchargée', ['file' => $name, 'admin' => $this->admin['email']]);
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public function migrate(): void
    {
        try {
            $applied = Migrator::run();
            flash('success', $applied ? 'Migrations appliquées : ' . implode(', ', $applied) . '.' : 'Aucune migration en attente.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/mise-a-jour');
    }

    public function restore(string $name): void
    {
        try {
            $result = (new Updater())->restore($name);
            log_message('update', 'Sauvegarde restaurée', ['backup' => $name, 'admin' => $this->admin['email']] + $result);
            flash('success', "Sauvegarde restaurée : version {$result['version']} ({$result['files']} fichier(s)). L'état précédent a lui-même été sauvegardé ({$result['backup']}).");
        } catch (\Throwable $e) {
            flash('error', 'Restauration impossible : ' . $e->getMessage());
        }
        redirect('admin/mise-a-jour');
    }
}
