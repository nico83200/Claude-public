<?php
declare(strict_types=1);

/** Tutoriels vidéo : page de visionnage (tous les utilisateurs) et gestion des vidéos (administrateurs). */
function videos_page(): void
{
    require_login();
    $list = video_list(is_admin());
    $cur = null;
    if ($uid = (string)input('v', '')) {
        foreach ($list as $v) {
            if ($v['uid'] === $uid) {
                $cur = $v;
            }
        }
    }
    render('user/videos', [
        'title' => 'Tutoriels vidéo',
        'videos' => $list,
        'cur' => $cur ?? ($list[0] ?? null),
        'start' => max(0, input_int('t')),
        'pending' => is_admin() ? (int)val("SELECT COUNT(*) FROM videos WHERE active = 1 AND (file IS NULL OR file = '')") : 0,
    ]);
}

/** Fichier vidéo, réservé aux utilisateurs connectés (avance rapide prise en charge). */
function video_file(): void
{
    require_login();
    $v = video_find((string)input('v', ''));
    if (!$v || !$v['active'] || !$v['ready'] || ($v['audience'] === 'admin' && !is_admin())) {
        abort(404, 'Vidéo introuvable.');
    }
    session_write_close(); // la lecture peut durer : on libère la session pour les autres onglets
    video_stream(video_path($v));
}

/** Fenêtre d'accueil : « Ne plus afficher » coché à la fermeture. */
function api_welcome_video(): void
{
    $u = require_login();
    if (input('off') === '1') {
        update('users', ['welcome_video_off' => 1], 'id = ?', [$u['id']]);
    }
    json_response(['ok' => true]);
}

/** Plus petite des limites d'envoi de fichier de PHP, en octets. */
function upload_limit(): int
{
    $bytes = function (string $v): int {
        $n = (int)$v;
        return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    };
    return min($bytes((string)ini_get('upload_max_filesize')), $bytes((string)ini_get('post_max_size')) ?: PHP_INT_MAX);
}

function admin_videos(): void
{
    require_admin();
    if (is_post()) {
        $id = input_int('id');
        $v = $id ? one('SELECT * FROM videos WHERE id = ?', [$id]) : null;
        $meta = fn() => [
            'title' => mb_substr(trim((string)input('title', '')), 0, 150),
            'description' => mb_substr(trim((string)input('description', '')), 0, 2000) ?: null,
            'keywords' => mb_substr(trim((string)input('keywords', '')), 0, 400) ?: null,
            'chapters' => json_encode(video_chapters_parse((string)input('chapters', '')), JSON_UNESCAPED_UNICODE),
            'audience' => input('audience') === 'admin' ? 'admin' : 'all',
            'position' => input_int('position'),
            'updated_at' => now(),
        ];
        switch ((string)input('action')) {
            case 'upload':
                $f = $_FILES['video'] ?? null;
                $data = $meta();
                if ($data['title'] === '') {
                    flash('error', 'Indiquez le titre de la vidéo.');
                    break;
                }
                if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                    flash('error', $f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                        ? 'Fichier trop volumineux pour la configuration PHP du serveur (limite : ' . round(upload_limit() / 1048576) . ' Mo).'
                        : 'Choisissez le fichier vidéo à envoyer.');
                    break;
                }
                $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                if (!in_array($mime, ['video/mp4', 'video/x-m4v', 'video/quicktime', 'application/mp4'], true)) {
                    flash('error', 'Format non pris en charge (' . $mime . ') : envoyez une vidéo MP4 (H.264), lisible sur tous les appareils.');
                    break;
                }
                if ($f['size'] > VIDEO_MAX_MB * 1048576) {
                    flash('error', 'Vidéo trop lourde (' . VIDEO_MAX_MB . ' Mo au maximum).');
                    break;
                }
                $uid = 'loc-' . bin2hex(random_bytes(5));
                $name = $uid . '.mp4';
                if (!move_uploaded_file($f['tmp_name'], videos_dir() . '/' . $name)) {
                    flash('error', 'Impossible d\'enregistrer la vidéo (droits d\'écriture du dossier storage/ ?).');
                    break;
                }
                insert('videos', $data + ['uid' => $uid, 'source' => 'local', 'file' => $name, 'size' => filesize(videos_dir() . '/' . $name),
                    'sha256' => hash_file('sha256', videos_dir() . '/' . $name), 'duration' => input_int('duration') ?: null, 'active' => 1, 'created_at' => now()]);
                audit('Tutoriel vidéo ajouté : ' . $data['title'], 'video');
                flash('success', 'Vidéo « ' . $data['title'] . ' » publiée : elle est visible dans « Tutoriels vidéo » et proposée par l\'aide.');
                break;

            case 'save':
                if (!$v) {
                    break;
                }
                // Les vidéos NLapps sont décrites par NLapps : seuls l'ordre et la visibilité se règlent ici
                $data = $v['source'] === 'local' ? $meta() : ['position' => input_int('position'), 'updated_at' => now()];
                if (isset($data['title']) && $data['title'] === '') {
                    flash('error', 'Le titre est obligatoire.');
                    break;
                }
                update('videos', $data + ['active' => input('active') ? 1 : 0], 'id = ?', [$v['id']]);
                flash('success', 'Vidéo mise à jour.');
                break;

            case 'delete':
                if ($v && $v['source'] === 'local') {
                    if ($v['file']) {
                        @unlink(videos_dir() . '/' . basename((string)$v['file']));
                    }
                    q('DELETE FROM videos WHERE id = ?', [$v['id']]);
                    audit('Tutoriel vidéo supprimé : ' . $v['title'], 'video');
                    flash('success', 'Vidéo supprimée.');
                }
                break;

            case 'welcome':
                $w = (string)input('welcome_video', '');
                set_setting('welcome_video', $w === 'none' || $w === '' || video_find($w) ? $w : '');
                if (input('reset')) {
                    q("UPDATE users SET welcome_video_off = 0 WHERE role NOT IN ('admin', 'buyer')");
                }
                flash('success', $w === 'none' ? 'Aucune vidéo ne s\'ouvrira à la connexion des salariés.' : 'Vidéo d\'accueil enregistrée' . (input('reset') ? ' : elle s\'ouvrira à la prochaine connexion de chaque salarié.' : '.'));
                break;

            case 'fetch':
                if (licence_managed()) {
                    licence_check(true);
                }
                flash('success', 'Vidéos NLapps : ' . videos_download_pending(10) . '.');
                break;
        }
        redirect('admin/videos');
    }
    render('admin/videos', [
        'title' => 'Tutoriels vidéo',
        'videos' => array_map('video_decode', array_merge(central_video_rows(), all('SELECT * FROM videos ORDER BY position, id'))),
        'limit' => upload_limit(),
        'managed' => licence_managed(),
        'welcome' => (string)setting('welcome_video', ''),
        'welcomeAuto' => (function () { foreach (video_list(false) as $v) { if ((int)$v['welcome'] === 1) { return $v; } } return null; })(),
    ]);
}
