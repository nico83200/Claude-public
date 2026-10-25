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

        $tmp = STORAGE_PATH . '/cache/update-' . bin2hex(random_bytes(6)) . '.zip';
        if (!move_uploaded_file($file['tmp_name'], $tmp)) {
            flash('error', 'Impossible d\'enregistrer le fichier téléversé.');
            redirect('admin/mise-a-jour');
        }

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
            log_message('update', 'Mise à jour refusée', ['file' => $file['name'], 'error' => $e->getMessage(), 'admin' => $this->admin['email']]);
            flash('error', 'Mise à jour annulée, aucun fichier n\'a été modifié : ' . $e->getMessage());
            redirect('admin/mise-a-jour');
        } finally {
            @unlink($tmp);
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
