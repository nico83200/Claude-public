<?php
/** Actions de la console (formulaires POST et appels AJAX avec l'en-tête X-CSRF). */
defined('HUB') || exit;

$action = (string)($_POST['action'] ?? '');
$ajax = !empty($_SERVER['HTTP_X_CSRF']);
$cid = (int)($_POST['c'] ?? 0);
// Comptes « conseiller » : conversations, FAQ, vidéos, réglages de disponibilité et leur propre compte ; le reste est réservé aux administrateurs
$adminActions = ['client_add', 'client_save', 'client_toggle', 'client_delete', 'client_extend', 'client_rotate', 'release_upload', 'release_toggle', 'release_delete',
    'grace_save', 'stripe_save', 'stripe_test', 'client_paylink', 'hub_update', 'hub_rollback', 'ai_save', 'app_save', 'app_delete', 'user_add', 'user_save', 'user_password', 'user_totp_reset'];
if (in_array($action, $adminActions, true) && $hubUser['role'] !== 'admin') {
    flash('Action réservée aux administrateurs de la console.', true);
    go('index.php');
}

switch ($action) {
    // ------------------------------------------------------------ Conversations
    case 'reply':
        $text = trim((string)($_POST['text'] ?? ''));
        $file = null;
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            try {
                $file = hub_store_image((string)file_get_contents($_FILES['image']['tmp_name']));
            } catch (RuntimeException $e) {
                $ajax ? json_out(['error' => $e->getMessage()], 422) : flash($e->getMessage(), true);
                go('index.php?c=' . $cid);
            }
        }
        if (($text !== '' || $file) && hone('SELECT id FROM conversations WHERE id = ?', [$cid])) {
            $id = hub_add_message($cid, 'agent', $text !== '' ? $text : 'Image', $file, $hubUser['name']);
            hq("UPDATE conversations SET unread = 0, status = CASE WHEN status = 'closed' THEN 'open' ELSE status END WHERE id = ?", [$cid]);
            if ($ajax) {
                json_out(['ok' => true, 'id' => $id]);
            }
        }
        go('index.php?c=' . $cid);

    case 'ai_suggest':
        $conv = hone('SELECT c.*, cl.name AS client, cl.app FROM conversations c JOIN clients cl ON cl.id = c.client_id WHERE c.id = ?', [$cid]);
        if (!$conv) {
            json_out(['error' => 'Conversation introuvable.'], 404);
        }
        try {
            json_out(['text' => hub_ai_suggest($conv)]);
        } catch (Throwable $e) {
            json_out(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'Suggestion indisponible pour le moment.'], 502);
        }

    case 'set_status':
        $status = in_array($_POST['status'] ?? '', ['open', 'pending', 'closed'], true) ? $_POST['status'] : 'open';
        $conv = hone('SELECT * FROM conversations WHERE id = ?', [$cid]);
        if ($conv) {
            hq('UPDATE conversations SET status = ?, unread = 0 WHERE id = ?', [$status, $cid]);
            $labels = ['open' => 'Conversation rouverte', 'pending' => 'En attente de votre réponse', 'closed' => 'Conversation résolue et clôturée par ' . hcfg('operator_name')];
            hub_add_message($cid, 'system', $labels[$status] . '.');
            if ($status === 'closed') {
                $sent = !empty($_POST['transcript']) && hub_send_transcript($conv);
                flash('Conversation résolue.' . ($sent ? ' Transcription envoyée à ' . $conv['user_email'] . '.' : (!empty($_POST['transcript']) ? ' La transcription n\'a pas pu être envoyée (envoi d\'e-mails indisponible sur ce serveur).' : '')), !$sent && !empty($_POST['transcript']));
            }
        }
        go('index.php?c=' . $cid);

    case 'availability':
        if (hsetting('availability_mode', 'manual') === 'auto') {
            hset('availability_mode', 'manual'); // un clic sur le bouton reprend la main sur les horaires
        }
        hset('online', ($_POST['online'] ?? '') === '1' ? '1' : '0');
        go((string)($_POST['back'] ?? 'index.php'));

    // ------------------------------------------------------------ Parc clients et licences
    case 'client_add':
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') {
            $key = hub_create_client($name, (string)($_POST['site'] ?? ''));
            $id = (int)hdb()->lastInsertId();
            hq('UPDATE clients SET app = ?, contact_email = ?, paid_until = ?, ai_option = ?, plan = ? WHERE id = ?', [
                hub_app((string)($_POST['app'] ?? ''))['slug'] ?? 'approvia',
                trim((string)($_POST['contact_email'] ?? '')) ?: null, ($_POST['paid_until'] ?? '') ?: null, !empty($_POST['ai_option']) ? 1 : 0,
                trim((string)($_POST['plan'] ?? '')) ?: 'Abonnement', $id,
            ]);
            $_SESSION['new_key'] = ['name' => $name, 'key' => $key];
        }
        go('index.php?p=clients');

    case 'client_save':
        $id = (int)($_POST['id'] ?? 0);
        $until = (string)($_POST['paid_until'] ?? '');
        hq('UPDATE clients SET name = ?, site = ?, contact_email = ?, plan = ?, paid_until = ?, ai_option = ?, status = ?, licence_note = ? WHERE id = ?', [
            mb_substr(trim((string)$_POST['name']), 0, 120) ?: 'Client', mb_substr(trim((string)($_POST['site'] ?? '')), 0, 200),
            trim((string)($_POST['contact_email'] ?? '')) ?: null, mb_substr(trim((string)($_POST['plan'] ?? '')), 0, 60) ?: 'Abonnement',
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) ? $until : null, !empty($_POST['ai_option']) ? 1 : 0,
            ($_POST['status'] ?? '') === 'suspended' ? 'suspended' : 'active', mb_substr(trim((string)($_POST['licence_note'] ?? '')), 0, 300) ?: null, $id,
        ]);
        flash('Licence mise à jour : elle est transmise à l\'installation du client en moins de 10 minutes (expiration ou suspension : utilisateurs déconnectés).');
        go('index.php?p=clients');

    case 'client_extend':
        $c = hone('SELECT * FROM clients WHERE id = ?', [(int)$_POST['id']]);
        if ($c) {
            $base = $c['paid_until'] && $c['paid_until'] > date('Y-m-d') ? $c['paid_until'] : date('Y-m-d');
            $months = max(1, min(36, (int)($_POST['months'] ?? 1)));
            hq('UPDATE clients SET paid_until = ? WHERE id = ?', [date('Y-m-d', strtotime($base . ' +' . $months . ' months')), $c['id']]);
            flash('Abonnement de « ' . $c['name'] . ' » prolongé de ' . $months . ' mois.');
        }
        go('index.php?p=clients');

    case 'client_toggle':
        hq('UPDATE clients SET active = 1 - active WHERE id = ?', [(int)$_POST['id']]);
        go('index.php?p=clients');

    case 'client_rotate':
        $c = hone('SELECT * FROM clients WHERE id = ?', [(int)$_POST['id']]);
        if ($c) {
            $_SESSION['new_key'] = ['name' => $c['name'], 'key' => hub_rotate_key((int)$c['id']), 'rotated' => true];
        }
        go('index.php?p=clients');

    // ------------------------------------------------------------ Versions publiées
    case 'release_upload':
        $f = $_FILES['package'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            flash('Envoi impossible' . ($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' : fichier trop volumineux pour la configuration PHP du serveur.' : '.'), true);
            go('index.php?p=releases');
        }
        try {
            $info = hub_inspect_package($f['tmp_name']);
            $app = hub_app((string)($_POST['app'] ?? ''))['slug'] ?? 'approvia';
            if (hone('SELECT id FROM releases WHERE app = ? AND version = ?', [$app, $info['version']])) {
                throw new RuntimeException('La version ' . $info['version'] . ' de ' . $app . ' est déjà publiée.');
            }
            $file = $app . '-' . $info['version'] . '-' . bin2hex(random_bytes(6)) . '.zip';
            if (!move_uploaded_file($f['tmp_name'], hub_releases_dir() . '/' . $file)) {
                throw new RuntimeException('Impossible d\'enregistrer le paquet (droits d\'écriture du dossier data/ ?).');
            }
            $path = hub_releases_dir() . '/' . $file;
            hq('INSERT INTO releases (app, version, notes, file, sha256, size, published, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
                $app, $info['version'], mb_substr($info['notes'], 0, 8000), $file, hash_file('sha256', $path), filesize($path), !empty($_POST['publish']) ? 1 : 0, hnow(),
            ]);
            flash('Version ' . $info['version'] . ' ' . (!empty($_POST['publish']) ? 'publiée : les installations la proposeront à leurs administrateurs.' : 'enregistrée (non publiée).'));
        } catch (RuntimeException $e) {
            flash($e->getMessage(), true);
        }
        go('index.php?p=releases');

    case 'release_toggle':
        hq('UPDATE releases SET published = 1 - published WHERE id = ?', [(int)$_POST['id']]);
        go('index.php?p=releases');

    case 'release_delete':
        $r = hone('SELECT * FROM releases WHERE id = ?', [(int)$_POST['id']]);
        if ($r) {
            @unlink(hub_releases_dir() . '/' . basename($r['file']));
            hq('DELETE FROM releases WHERE id = ?', [$r['id']]);
            flash('Version ' . $r['version'] . ' supprimée.');
        }
        go('index.php?p=releases');

    // ------------------------------------------------------------ Tutoriels vidéo
    case 'video_upload':
    case 'video_save':
        $id = (int)($_POST['id'] ?? 0);
        $meta = [
            'title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150),
            'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 2000) ?: null,
            'keywords' => mb_substr(trim((string)($_POST['keywords'] ?? '')), 0, 400) ?: null,
            'chapters' => json_encode(hub_chapters_parse((string)($_POST['chapters'] ?? '')), JSON_UNESCAPED_UNICODE),
            'audience' => ($_POST['audience'] ?? '') === 'admin' ? 'admin' : 'all',
            'app' => preg_replace('/[^a-z0-9_*-]/', '', strtolower((string)($_POST['app'] ?? '*'))) ?: '*',
            'position' => (int)($_POST['position'] ?? 0),
            'welcome' => !empty($_POST['welcome']) ? 1 : 0,
        ];
        if ($meta['title'] === '') {
            flash('Indiquez le titre de la vidéo.', true);
            go('index.php?p=videos');
        }
        if ($action === 'video_save') {
            hq('UPDATE videos SET title = ?, description = ?, keywords = ?, chapters = ?, audience = ?, app = ?, position = ?, welcome = ?, updated_at = ? WHERE id = ?', [...array_values($meta), hnow(), $id]);
            flash('Vidéo mise à jour : les installations reçoivent les changements à leur prochaine synchronisation.');
            go('index.php?p=videos');
        }
        $f = $_FILES['video'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            flash('Envoi impossible' . ($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' : vidéo trop volumineuse pour la configuration PHP du serveur.' : '.'), true);
            go('index.php?p=videos');
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!in_array($mime, ['video/mp4', 'video/x-m4v', 'video/quicktime', 'application/mp4'], true)) {
            flash('Format non pris en charge (' . $mime . ') : envoyez une vidéo MP4 (H.264).', true);
            go('index.php?p=videos');
        }
        $uid = 'nl-' . bin2hex(random_bytes(5));
        $file = $uid . '.mp4';
        if (!move_uploaded_file($f['tmp_name'], hub_videos_dir() . '/' . $file)) {
            flash('Impossible d\'enregistrer la vidéo (droits d\'écriture du dossier data/ ?).', true);
            go('index.php?p=videos');
        }
        $path = hub_videos_dir() . '/' . $file;
        hq('INSERT INTO videos (uid, title, description, keywords, chapters, audience, app, position, welcome, file, sha256, size, duration, published, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$uid, ...array_values($meta), $file, hash_file('sha256', $path), filesize($path),
            (int)($_POST['duration'] ?? 0) ?: null, !empty($_POST['publish']) ? 1 : 0, hnow(), hnow()]);
        flash('Vidéo « ' . $meta['title'] . ' » ' . (!empty($_POST['publish']) ? 'publiée : elle arrivera dans les installations à leur prochaine synchronisation.' : 'enregistrée (non publiée).'));
        go('index.php?p=videos');

    case 'video_toggle':
        hq('UPDATE videos SET published = 1 - published, updated_at = ? WHERE id = ?', [hnow(), (int)$_POST['id']]);
        go('index.php?p=videos');

    case 'video_delete':
        $v = hone('SELECT * FROM videos WHERE id = ?', [(int)$_POST['id']]);
        if ($v) {
            @unlink(hub_videos_dir() . '/' . basename($v['file']));
            hq('DELETE FROM videos WHERE id = ?', [$v['id']]);
            flash('Vidéo supprimée : elle disparaîtra des installations à leur prochaine synchronisation.');
        }
        go('index.php?p=videos');

    // ------------------------------------------------------------ FAQ partagée
    case 'faq_save':
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            preg_replace('/[^a-z0-9_*-]/', '', strtolower((string)($_POST['app'] ?? '*'))) ?: '*',
            mb_substr(trim((string)$_POST['question']), 0, 200), mb_substr(trim((string)($_POST['keywords'] ?? '')), 0, 300),
            mb_substr(trim((string)$_POST['answer']), 0, 2000), mb_substr(trim((string)($_POST['link_label'] ?? '')), 0, 60) ?: null,
            preg_replace('#[^a-z0-9/_-]#', '', strtolower((string)($_POST['link_route'] ?? ''))) ?: null,
            !empty($_POST['admin_only']) ? 1 : 0, isset($_POST['active']) || !$id ? 1 : 0, hnow(),
        ];
        if ($data[1] === '' || $data[3] === '') {
            flash('Indiquez la question et la réponse.', true);
        } elseif ($id) {
            hq('UPDATE faq SET app = ?, question = ?, keywords = ?, answer = ?, link_label = ?, link_route = ?, admin_only = ?, active = ?, updated_at = ? WHERE id = ?', [...$data, $id]);
            flash('Question mise à jour : le chatbot de vos clients la connaîtra à leur prochaine synchronisation.');
        } else {
            hq('INSERT INTO faq (app, question, keywords, answer, link_label, link_route, admin_only, active, updated_at, source_conv, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [...$data, (int)($_POST['source_conv'] ?? 0) ?: null, hnow()]);
            flash('Question ajoutée à la FAQ partagée : le chatbot de vos clients y répondra désormais.');
        }
        go('index.php?p=faq');

    case 'faq_delete':
        hq('DELETE FROM faq WHERE id = ?', [(int)$_POST['id']]);
        go('index.php?p=faq');

    // ------------------------------------------------------------ Réponses rapides
    case 'quick_save':
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 80);
        $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 2000);
        if ($title !== '' && $body !== '') {
            if ($id = (int)($_POST['id'] ?? 0)) {
                hq('UPDATE quick_replies SET title = ?, body = ? WHERE id = ?', [$title, $body, $id]);
            } else {
                hq('INSERT INTO quick_replies (title, body, position) VALUES (?, ?, ?)', [$title, $body, (int)(hone('SELECT MAX(position) m FROM quick_replies')['m'] ?? 0) + 1]);
            }
        }
        go('index.php?p=settings#quick');

    case 'quick_delete':
        hq('DELETE FROM quick_replies WHERE id = ?', [(int)$_POST['id']]);
        go('index.php?p=settings#quick');

    // ------------------------------------------------------------ Réglages
    case 'schedule_save':
        hset('availability_mode', ($_POST['mode'] ?? '') === 'auto' ? 'auto' : 'manual');
        $sched = [];
        foreach (range(1, 7) as $d) {
            $slots = [];
            foreach (preg_split('/[,;]/', (string)($_POST['day'][$d] ?? '')) as $slot) {
                if (preg_match('/(\d{1,2})[:h](\d{2})\s*-\s*(\d{1,2})[:h](\d{2})/', $slot, $m)) {
                    $slots[] = [sprintf('%02d:%s', $m[1], $m[2]), sprintf('%02d:%s', $m[3], $m[4])];
                }
            }
            if ($slots) {
                $sched[$d] = $slots;
            }
        }
        hset('schedule', json_encode($sched));
        hset('away_message', mb_substr(trim((string)($_POST['away_message'] ?? '')), 0, 300));
        flash('Disponibilité enregistrée.');
        go('index.php?p=settings');

    case 'ai_save':
        if (trim((string)($_POST['api_key'] ?? '')) !== '') {
            hset('anthropic_api_key', trim((string)$_POST['api_key']));
        }
        if (!empty($_POST['remove_key'])) {
            hset('anthropic_api_key', null);
        }
        hset('anthropic_model', preg_replace('/[^a-z0-9.-]/', '', (string)($_POST['model'] ?? '')) ?: 'claude-opus-5-5');
        flash('Réglages de l\'IA enregistrés.');
        go('index.php?p=settings#ai');

    // ------------------------------------------------------------ Mon compte (identifiant, nom, mot de passe, double authentification)
    case 'account_save':
        $username = trim((string)($_POST['username'] ?? ''));
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80);
        if (!hub_valid_username($username) || $name === '') {
            flash('Indiquez votre nom et un identifiant valide (3 caractères minimum : lettres, chiffres, point, tiret, @).', true);
        } elseif (hone('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $hubUser['id']])) {
            flash('Cet identifiant est déjà utilisé.', true);
        } else {
            hq('UPDATE users SET username = ?, name = ?, email = ? WHERE id = ?', [$username, $name, mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190) ?: null, $hubUser['id']]);
            hset('legacy_login', null);
            flash('Compte mis à jour. Identifiant de connexion : ' . $username . '.');
        }
        go('index.php?p=account');

    case 'password_change':
        if (!password_verify((string)($_POST['current'] ?? ''), (string)$hubUser['password_hash'])) {
            flash('Mot de passe actuel incorrect.', true);
        } elseif (mb_strlen((string)$_POST['new']) < 10 || $_POST['new'] !== ($_POST['new2'] ?? '')) {
            flash('Nouveau mot de passe : 10 caractères minimum, saisi deux fois à l\'identique.', true);
        } else {
            hq('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string)$_POST['new'], PASSWORD_DEFAULT), $hubUser['id']]);
            flash('Mot de passe modifié.');
        }
        go('index.php?p=account');

    case 'totp_start':
        $_SESSION['totp_new'] = base32_encode(random_bytes(20));
        go('index.php?p=account#security');

    case 'totp_enable':
        $secret = (string)($_SESSION['totp_new'] ?? '');
        if ($secret && totp_verify($secret, (string)($_POST['code'] ?? ''))) {
            hq('UPDATE users SET totp_secret = ? WHERE id = ?', [$secret, $hubUser['id']]);
            unset($_SESSION['totp_new']);
            flash('Double authentification activée : un code vous sera demandé à chaque connexion.');
        } else {
            flash('Code incorrect : vérifiez l\'heure de votre téléphone et réessayez.', true);
        }
        go('index.php?p=account#security');

    case 'totp_disable':
        if (password_verify((string)($_POST['current'] ?? ''), (string)$hubUser['password_hash']) && totp_verify((string)$hubUser['totp_secret'], (string)($_POST['code'] ?? ''))) {
            hq('UPDATE users SET totp_secret = NULL WHERE id = ?', [$hubUser['id']]);
            flash('Double authentification désactivée.');
        } else {
            flash('Mot de passe ou code incorrect.', true);
        }
        go('index.php?p=account#security');

    // ------------------------------------------------------------ Comptes de la console (administrateurs)
    case 'user_add':
        $username = trim((string)($_POST['username'] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        if (!hub_valid_username($username) || trim((string)($_POST['name'] ?? '')) === '') {
            flash('Indiquez le nom et un identifiant valide (3 caractères minimum : lettres, chiffres, point, tiret, @).', true);
        } elseif (hone('SELECT id FROM users WHERE username = ?', [$username])) {
            flash('Cet identifiant est déjà utilisé.', true);
        } elseif (mb_strlen($pw) < 10) {
            flash('Mot de passe provisoire : 10 caractères minimum.', true);
        } else {
            hq('INSERT INTO users (username, name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$username, mb_substr(trim((string)$_POST['name']), 0, 80),
                mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190) ?: null, password_hash($pw, PASSWORD_DEFAULT), ($_POST['role'] ?? '') === 'agent' ? 'agent' : 'admin', hnow()]);
            flash('Compte « ' . $username . ' » créé. Communiquez-lui son identifiant et son mot de passe provisoire : il pourra le changer dans « Mon compte ».');
        }
        go('index.php?p=users');

    case 'user_save':
        $u = hone('SELECT * FROM users WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        $role = ($_POST['role'] ?? '') === 'agent' ? 'agent' : 'admin';
        $active = !empty($_POST['active']) ? 1 : 0;
        $admins = (int)hone("SELECT COUNT(*) n FROM users WHERE role = 'admin' AND active = 1 AND id <> ?", [(int)($u['id'] ?? 0)])['n'];
        if (!$u) {
            break;
        }
        if (($role !== 'admin' || !$active) && $admins === 0) {
            flash('Il doit rester au moins un administrateur actif.', true);
        } else {
            hq('UPDATE users SET name = ?, email = ?, role = ?, active = ? WHERE id = ?', [mb_substr(trim((string)($_POST['name'] ?? $u['name'])), 0, 80) ?: $u['name'],
                mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190) ?: null, $role, $active, $u['id']]);
            flash('Compte « ' . $u['username'] . ' » mis à jour.');
        }
        go('index.php?p=users');

    case 'user_password':
        $pw = (string)($_POST['password'] ?? '');
        if (mb_strlen($pw) < 10) {
            flash('Mot de passe provisoire : 10 caractères minimum.', true);
        } else {
            hq('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), (int)($_POST['id'] ?? 0)]);
            flash('Nouveau mot de passe enregistré : communiquez-le à la personne concernée.');
        }
        go('index.php?p=users');

    case 'user_totp_reset':
        hq('UPDATE users SET totp_secret = NULL WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        flash('Double authentification réinitialisée : la personne pourra la reconfigurer dans « Mon compte ».');
        go('index.php?p=users');

    // ------------------------------------------------------------ Applications gérées
    case 'app_save':
        $orig = (string)($_POST['orig'] ?? '');
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
        $slug = $orig !== '' ? $orig : hub_slug((string)($_POST['slug'] ?? '') ?: $name);
        $color = preg_match('/^#[0-9a-f]{6}$/i', (string)($_POST['color'] ?? '')) ? (string)$_POST['color'] : '#4f46e5';
        $prices = [max(0, (float)str_replace(',', '.', (string)($_POST['price_base'] ?? '0'))), max(0, (float)str_replace(',', '.', (string)($_POST['price_ai'] ?? '0')))];
        if ($name === '' || $slug === '') {
            flash('Indiquez le nom de l\'application.', true);
        } elseif ($orig !== '') {
            hq('UPDATE apps SET name = ?, color = ?, price_base = ?, price_ai = ?, position = ? WHERE slug = ?', [$name, $color, ...$prices, (int)($_POST['position'] ?? 0), $orig]);
            flash('Application « ' . $name . ' » mise à jour.');
        } elseif (hub_app($slug)) {
            flash('L\'identifiant « ' . $slug . ' » est déjà utilisé par une autre application.', true);
        } else {
            hq('INSERT INTO apps (slug, name, color, price_base, price_ai, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$slug, $name, $color, ...$prices, count(hub_apps()), hnow()]);
            flash('Application « ' . $name . ' » ajoutée : elle a désormais son sous-menu (clients, versions, FAQ, vidéos). Identifiant technique : ' . $slug . '.');
            go('index.php?p=clients&app=' . rawurlencode($slug));
        }
        go('index.php?p=apps');

    case 'app_delete':
        $slug = (string)($_POST['slug'] ?? '');
        $used = (int)hone('SELECT COUNT(*) n FROM clients WHERE app = ?', [$slug])['n'] + (int)hone('SELECT COUNT(*) n FROM releases WHERE app = ?', [$slug])['n'];
        if ($used) {
            flash('Cette application a encore des clients ou des versions : supprimez-les d\'abord.', true);
        } elseif (count(hub_apps()) <= 1) {
            flash('Gardez au moins une application.', true);
        } else {
            hq('DELETE FROM apps WHERE slug = ?', [$slug]);
            flash('Application supprimée.');
        }
        go('index.php?p=apps');

    case 'push_subscribe':
        $sub = json_decode((string)($_POST['sub'] ?? ''), true);
        if (!is_array($sub) || empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth']) || !str_starts_with((string)$sub['endpoint'], 'https://')) {
            json_out(['error' => 'Abonnement invalide.'], 422);
        }
        hq('INSERT INTO push_subs (endpoint, p256dh, auth, label, created_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT(endpoint) DO UPDATE SET p256dh = excluded.p256dh, auth = excluded.auth',
            [$sub['endpoint'], $sub['keys']['p256dh'], $sub['keys']['auth'], mb_substr((string)($_POST['label'] ?? ''), 0, 120), hnow()]);
        json_out(['ok' => true]);

    case 'push_unsubscribe':
        hq('DELETE FROM push_subs WHERE id = ? OR endpoint = ?', [(int)($_POST['id'] ?? 0), (string)($_POST['endpoint'] ?? '')]);
        $ajax ? json_out(['ok' => true]) : go('index.php?p=settings#notif');

    case 'push_test':
        $n = hub_push_all('Test Assistance NLapps', 'Les notifications fonctionnent sur cet appareil.', hub_base_url());
        flash($n ? 'Notification envoyée à ' . $n . ' appareil(s).' : 'Aucun appareil n\'a reçu la notification : activez-les depuis votre téléphone.', !$n);
        go('index.php?p=settings#notif');

    // ------------------------------------------------------------ Licences : délai de grâce
    case 'grace_save':
        hset('grace_days', (string)max(0, min(60, (int)($_POST['grace_days'] ?? 0))));
        flash(hub_grace_days() ? 'Délai de grâce : ' . hub_grace_days() . ' jour(s) après l\'échéance.' : 'Coupure immédiate à l\'échéance : les utilisateurs d\'un client dont la licence expire sont déconnectés.');
        go('index.php?p=settings#licences');

    // ------------------------------------------------------------ Paiement en ligne (Stripe)
    case 'stripe_save':
        $sk = trim((string)($_POST['stripe_secret_key'] ?? ''));
        $wh = trim((string)($_POST['stripe_webhook_secret'] ?? ''));
        if ($sk !== '' && !preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $sk)) {
            flash('Clé secrète Stripe invalide (elle commence par sk_live_ ou sk_test_).', true);
            go('index.php?p=settings#paiement');
        }
        if ($wh !== '' && !str_starts_with($wh, 'whsec_')) {
            flash('Secret du webhook invalide (il commence par whsec_).', true);
            go('index.php?p=settings#paiement');
        }
        if ($sk !== '') {
            hset('stripe_secret_key', $sk);
        }
        if ($wh !== '') {
            hset('stripe_webhook_secret', $wh);
        }
        if (!empty($_POST['stripe_clear'])) {
            hset('stripe_secret_key', null);
            hset('stripe_webhook_secret', null);
        }
        hset('billing_vat', (string)max(0, min(30, (float)str_replace(',', '.', (string)($_POST['billing_vat'] ?? '20')))));
        flash('Paiement en ligne enregistré' . (hub_stripe_ready() ? (hub_stripe_test_mode() ? ' (mode test Stripe).' : '.') : ' : désactivé.'));
        go('index.php?p=settings#paiement');

    case 'stripe_test':
        try {
            $bal = hub_stripe('GET', '/v1/balance');
            flash('Connexion à Stripe réussie' . (hub_stripe_test_mode() ? ' (mode test)' : '') . ' : solde disponible ' . number_format(((int)($bal['available'][0]['amount'] ?? 0)) / 100, 2, ',', ' ') . ' €.');
        } catch (Throwable $e) {
            flash($e->getMessage(), true);
        }
        go('index.php?p=settings#paiement');

    case 'client_paylink':
        $c = hone('SELECT * FROM clients WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if (!$c) {
            go('index.php');
        }
        $url = hub_pay_url($c);
        if (!empty($_POST['send'])) {
            if (!$c['contact_email']) {
                flash('Renseignez d\'abord l\'e-mail du contact de ' . $c['name'] . '.', true);
                go('index.php?p=clients&app=' . $c['app']);
            }
            $ttc = number_format(hub_billing_summary($c)['monthly_ttc'] / 100, 2, ',', ' ');
            $headers = 'From: ' . hcfg('operator_name') . ' <' . hcfg('from_email') . ">\r\nReply-To: " . hcfg('notify_email') . "\r\nContent-Type: text/plain; charset=UTF-8";
            $ok = @mail((string)$c['contact_email'], '=?UTF-8?B?' . base64_encode('Votre abonnement ' . (hub_app($c['app'])['name'] ?? 'NLapps') . ' : paiement automatique') . '?=',
                "Bonjour,\n\nPour régler votre abonnement (" . $ttc . " € TTC par mois) sans y penser, mettez en place le paiement automatique par carte bancaire ou prélèvement SEPA :\n\n"
                . $url . "\n\nLa période déjà réglée est conservée : le premier paiement aura lieu à son échéance. Le paiement est sécurisé par Stripe ; vous retrouverez vos factures au même endroit.\n\nMerci de votre confiance,\n" . hcfg('operator_name'), $headers);
            flash($ok ? 'Lien de paiement envoyé à ' . $c['contact_email'] . '.' : 'Envoi impossible depuis ce serveur : copiez le lien et transmettez-le.', !$ok);
        }
        go('index.php?p=clients&app=' . $c['app'] . '#client-' . $c['id']);

    // ------------------------------------------------------------ Mise à jour du centre d'assistance
    case 'hub_update':
    case 'hub_rollback':
        if (!password_verify((string)($_POST['password'] ?? ''), (string)$hubUser['password_hash'])) {
            flash('Mot de passe incorrect : opération annulée.', true);
            go('index.php?p=update');
        }
        try {
            if ($action === 'hub_update') {
                $f = $_FILES['package'] ?? null;
                if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                    throw new RuntimeException('Envoi impossible' . ($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' : fichier trop volumineux pour la configuration PHP (utilisez le paquet sans vendor/).' : '.'));
                }
                $r = hub_update_apply($f['tmp_name'], !empty($_POST['allow_same']));
                flash('Centre d\'assistance mis à jour : ' . $r['from'] . ' → ' . $r['version'] . ' (' . $r['files'] . ' fichiers). Sauvegarde de la version précédente : ' . $r['backup'] . '.');
            } else {
                $r = hub_rollback((string)($_POST['backup'] ?? ''));
                flash('Version ' . $r['version'] . ' restaurée (' . $r['files'] . ' fichiers).');
            }
        } catch (RuntimeException $e) {
            flash($e->getMessage(), true);
        }
        go('index.php?p=update');

    case 'logout':
        session_destroy();
        go('index.php');
}
go('index.php');
