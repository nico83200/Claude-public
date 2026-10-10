<?php
/** Actions de la console (formulaires POST et appels AJAX avec l'en-tête X-CSRF). */
defined('HUB') || exit;

$action = (string)($_POST['action'] ?? '');
$ajax = !empty($_SERVER['HTTP_X_CSRF']);
$cid = (int)($_POST['c'] ?? 0);
// Comptes « conseiller » : conversations, FAQ, vidéos, réglages de disponibilité et leur propre compte ; le reste est réservé aux administrateurs
$adminActions = ['client_add', 'client_toggle', 'client_rotate', 'hub_update', 'hub_rollback', 'ai_save', 'app_save', 'app_delete', 'user_add', 'user_save', 'user_password',
    'user_totp_reset', 'console_key', 'console_unlink'];
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

    // ------------------------------------------------------------ Accès au chat (une clé par client)
    case 'client_add':
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') {
            $key = hub_create_client($name, (string)($_POST['site'] ?? ''));
            hq('UPDATE clients SET app = ? WHERE id = ?', [hub_app((string)($_POST['app'] ?? ''))['slug'] ?? 'centriva', (int)hdb()->lastInsertId()]);
            $_SESSION['new_key'] = ['name' => $name, 'key' => $key];
        }
        go('index.php?p=access');

    case 'client_toggle':
        hq('UPDATE clients SET active = 1 - active WHERE id = ?', [(int)$_POST['id']]);
        go('index.php?p=access');

    case 'client_rotate':
        $c = hone('SELECT * FROM clients WHERE id = ?', [(int)$_POST['id']]);
        if ($c) {
            $_SESSION['new_key'] = ['name' => $c['name'], 'key' => hub_rotate_key((int)$c['id']), 'rotated' => true];
        }
        go('index.php?p=access');

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
        if ($name === '' || $slug === '') {
            flash('Indiquez le nom de l\'application.', true);
        } elseif ($orig !== '') {
            hq('UPDATE apps SET name = ?, color = ?, position = ? WHERE slug = ?', [$name, $color, (int)($_POST['position'] ?? 0), $orig]);
            flash('Application « ' . $name . ' » mise à jour.');
        } elseif (hub_app($slug)) {
            flash('L\'identifiant « ' . $slug . ' » est déjà utilisé par une autre application.', true);
        } else {
            hq('INSERT INTO apps (slug, name, color, position, created_at) VALUES (?, ?, ?, ?, ?)', [$slug, $name, $color, count(hub_apps()), hnow()]);
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

    // ------------------------------------------------------------ Console de la plateforme Centriva
    case 'console_key':
        $k = 'nlc_' . bin2hex(random_bytes(24));
        hset('console_key_hash', hash('sha256', $k));
        flash('Clé de liaison (affichée une seule fois) : ' . $k . ' — collez-la dans la console Centriva, menu Assistance, avec l\'adresse ' . preg_replace('/index\.php$/', 'api.php', hub_base_url()) . '.');
        go('index.php?p=settings#console');

    case 'console_unlink':
        hset('console_key_hash', null);
        hq('UPDATE apps SET console_url = NULL');
        flash('Console déliée : les pages de gestion sont de nouveau disponibles ici.');
        go('index.php?p=settings#console');

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
