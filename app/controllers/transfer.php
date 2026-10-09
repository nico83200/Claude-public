<?php
declare(strict_types=1);

/** Export / import complet (administrateur) : voir app/transfer.php. */
function admin_transfer(): void
{
    $me = require_superadmin();
    $pending = $_SESSION['transfer_pending'] ?? null; // archive envoyée, en attente de confirmation
    if (is_post()) {
        $action = (string)input('action');
        try {
            switch ($action) {
                case 'export':
                    $pw = (string)($_POST['password'] ?? '');
                    if ($pw !== (string)($_POST['confirm'] ?? '')) {
                        throw new RuntimeException('Les deux mots de passe de l\'archive ne correspondent pas.');
                    }
                    $file = transfer_export($pw, input('files') === '1');
                    audit('Export complet de la base', 'transfer', null, input('files') === '1' ? 'avec les fichiers' : 'sans les fichiers');
                    session_write_close();
                    $name = 'centriva-export-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', (string)(setting('company_name') ?: app_name())) ?: 'centriva')) . '-' . date('Y-m-d') . '.zip';
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . trim($name, '-') . '"');
                    header('Content-Length: ' . filesize($file));
                    header('Cache-Control: no-store');
                    readfile($file);
                    @unlink($file);
                    exit;

                case 'upload':
                    $f = $_FILES['archive'] ?? null;
                    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                        throw new RuntimeException($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                            ? 'Archive trop volumineuse pour la configuration PHP du serveur (limite : ' . round(upload_limit_bytes() / 1048576) . ' Mo) : exportez sans les fichiers, ou demandez à l\'hébergeur d\'augmenter upload_max_filesize.'
                            : 'Choisissez l\'archive d\'export (.zip).');
                    }
                    @mkdir(storage_path('imports'), 0750, true);
                    $dest = storage_path('imports/transfer-' . bin2hex(random_bytes(8)) . '.zip');
                    move_uploaded_file($f['tmp_name'], $dest);
                    $pw = (string)($_POST['password'] ?? '');
                    try {
                        $m = transfer_inspect($dest, $pw);
                    } catch (Throwable $e) {
                        @unlink($dest);
                        throw $e;
                    }
                    if ($pending) {
                        @unlink((string)$pending['file']);
                    }
                    // Le mot de passe de l'archive reste en session le temps de la confirmation (jamais écrit sur disque)
                    $_SESSION['transfer_pending'] = ['file' => $dest, 'password' => $pw, 'manifest' => $m, 'at' => time()];
                    redirect('admin/transfer');

                case 'cancel':
                    if ($pending) {
                        @unlink((string)$pending['file']);
                    }
                    unset($_SESSION['transfer_pending']);
                    flash('info', 'Import annulé : rien n\'a été modifié.');
                    redirect('admin/transfer');

                case 'import':
                    if (!$pending || !is_file((string)$pending['file']) || time() - (int)$pending['at'] > 3600) {
                        unset($_SESSION['transfer_pending']);
                        throw new RuntimeException('Archive expirée : envoyez-la de nouveau.');
                    }
                    if (mb_strtoupper(trim((string)input('confirm_word'))) !== 'REMPLACER') {
                        throw new RuntimeException('Saisissez REMPLACER pour confirmer le remplacement des données.');
                    }
                    if (!password_verify((string)($_POST['admin_password'] ?? ''), (string)$me['password_hash'])) {
                        throw new RuntimeException('Votre mot de passe est incorrect.');
                    }
                    $r = transfer_import((string)$pending['file'], (string)$pending['password']);
                    @unlink((string)$pending['file']);
                    // Les comptes viennent désormais de l'archive : chacun se reconnecte avec ses identifiants d'origine
                    logout_user();
                    flash('success', 'Import terminé : ' . number_format($r['stats']['lignes'], 0, ',', ' ') . ' enregistrements et ' . $r['stats']['fichiers']
                        . ' fichier(s) de « ' . $r['name'] . ' ». Connectez-vous avec un compte de la base importée. Sauvegarde de la base précédente : storage/backups/' . $r['backup'] . '.');
                    redirect('login');
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('admin/transfer');
        }
    }
    render('admin/transfer', [
        'title' => 'Export et import',
        'counts' => transfer_counts(),
        'pending' => $pending,
        'limit' => upload_limit_bytes(),
        'filesSize' => array_sum(array_map(fn($d) => array_sum(array_map('filesize', array_filter(glob($d . '/*') ?: [], 'is_file'))), transfer_dirs())),
    ]);
}

/** Plus petite des limites d'envoi de fichier de PHP, en octets. */
function upload_limit_bytes(): int
{
    $b = function (string $v): int {
        $n = (int)$v;
        return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    };
    return min($b((string)ini_get('upload_max_filesize')), $b((string)ini_get('post_max_size')) ?: PHP_INT_MAX);
}
