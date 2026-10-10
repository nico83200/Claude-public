<?php
declare(strict_types=1);

function auth_login(): void
{
    if (user()) {
        redirect(is_admin() ? 'admin' : 'dashboard');
    }
    $error = null;
    $email = '';
    if (is_post()) {
        $email = mb_strtolower((string)input('email'));
        $password = (string)($_POST['password'] ?? '');

        // Anti-force brute : échecs comptés en base, par compte et par adresse IP (15 minutes)
        if (login_blocked($email)) {
            $error = 'Trop de tentatives infructueuses. Réessayez dans 15 minutes ou utilisez « Mot de passe oublié ».';
            audit('Connexion bloquée', 'user', null, $email);
        } else {
            $u = one('SELECT * FROM users WHERE email = ?', [$email]);
            // Plateforme : le super administrateur se connecte sur la même page que tout le monde
            if ((!$u || !password_verify($password, $u['password_hash'])) && instances_enabled() && ($sa = superadmin_find($email)) && password_verify($password, (string)$sa['hash'])) {
                central_superadmin_enter($sa);
            }
            if (!$u || !password_verify($password, $u['password_hash'])) {
                login_record($email, false);
                $error = 'Identifiants incorrects.';
            } elseif ($u['status'] === 'pending') {
                $error = 'Votre compte est en attente de validation par un administrateur.';
            } elseif ($u['status'] !== 'active') {
                $error = 'Ce compte est désactivé.';
            } else {
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
                }
                if (licence_platform() && licence_status() === 'expired' && !is_superadmin($u)) {
                    $error = 'L\'abonnement de votre établissement a expiré : l\'accès reviendra dès son renouvellement. Prévenez l\'administrateur de ' . app_name() . ' : il peut le renouveler en se connectant.';
                    render('auth/login', ['error' => $error, 'email' => $email], 'layout_auth');
                    return;
                }
                if (user_has_2fa($u)) { // deuxième étape : code de l'application d'authentification
                    session_regenerate_id(true);
                    $_SESSION['2fa_uid'] = (int)$u['id'];
                    $_SESSION['2fa_at'] = time();
                    redirect('login/2fa');
                }
                login_record($email, true);
                login_user($u);
                redirect(is_admin($u) ? 'admin' : 'dashboard');
            }
        }
    }
    render('auth/login', ['error' => $error, 'email' => $email], 'layout_auth');
}

/**
 * Arrivée depuis la connexion commune de centriva.fr : jeton à usage unique, signé avec la clé de cet espace
 * (mot de passe déjà vérifié). La double authentification éventuelle est demandée ici, comme d'habitude.
 */
function auth_sso(): void
{
    $uid = central_handoff_verify((string)input('t', ''));
    $u = $uid ? one("SELECT * FROM users WHERE id = ? AND status = 'active'", [$uid]) : null;
    if (!$u) {
        flash('error', 'Lien de connexion expiré : reconnectez-vous.');
        redirect('login');
    }
    if (user_has_2fa($u)) {
        session_regenerate_id(true);
        $_SESSION['2fa_uid'] = (int)$u['id'];
        $_SESSION['2fa_at'] = time();
        redirect('login/2fa');
    }
    login_record($u['email'], true);
    login_user($u);
    redirect(is_admin($u) ? 'admin' : 'dashboard');
}

/** Deuxième étape de connexion : code à 6 chiffres (5 minutes après le mot de passe). */
function auth_2fa(): void
{
    $uid = (int)($_SESSION['2fa_uid'] ?? 0);
    if (!$uid || (int)($_SESSION['2fa_at'] ?? 0) < time() - 300) {
        unset($_SESSION['2fa_uid'], $_SESSION['2fa_at']);
        flash('error', 'Session expirée : reconnectez-vous.');
        redirect('login');
    }
    $u = one("SELECT * FROM users WHERE id = ? AND status = 'active'", [$uid]);
    $error = null;
    if ($u && is_post()) {
        if (login_blocked($u['email'])) {
            $error = 'Trop de tentatives infructueuses. Réessayez dans 15 minutes.';
        } elseif (user_totp_verify($u, (string)input('code', ''))) {
            unset($_SESSION['2fa_uid'], $_SESSION['2fa_at']);
            login_record($u['email'], true);
            login_user($u);
            redirect(is_admin($u) ? 'admin' : 'dashboard');
        } else {
            login_record($u['email'], false);
            $error = 'Code incorrect. Vérifiez l\'heure de votre téléphone et saisissez le code affiché actuellement.';
        }
    }
    render('auth/login_2fa', ['error' => $error], 'layout_auth');
}

function auth_register(): void
{
    if (setting('allow_registration', '1') !== '1') {
        flash('error', 'Les inscriptions sont fermées. Contactez le service achats.');
        redirect('login');
    }
    $centers = all('SELECT id, name, city FROM centers WHERE active = 1 ORDER BY name');
    $errors = [];
    $old = [];
    if (is_post()) {
        $old = [
            'first_name' => (string)input('first_name'), 'last_name' => (string)input('last_name'),
            'email' => mb_strtolower((string)input('email')), 'job' => (string)input('job'),
            'phone' => (string)input('phone'),
        ];
        $password = (string)($_POST['password'] ?? '');
        $wanted = array_map('intval', (array)($_POST['centers'] ?? []));
        if ($old['first_name'] === '' || $old['last_name'] === '') {
            $errors[] = 'Nom et prénom sont obligatoires.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        } elseif (val('SELECT COUNT(*) FROM users WHERE email = ?', [$old['email']])) {
            $errors[] = 'Un compte existe déjà avec cette adresse.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if (!$wanted) {
            $errors[] = 'Sélectionnez au moins un centre.';
        }
        if (!$errors) {
            insert('users', $old + [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'user', 'status' => 'pending',
                'requested_centers' => implode(',', $wanted), 'created_at' => now(),
            ]);
            notify(admin_ids(true), 'account_pending', 'Compte à valider : ' . $old['first_name'] . ' ' . $old['last_name'],
                ($old['job'] ?: 'Fonction non précisée') . ' — ' . $old['email'], url('admin/users', ['status' => 'pending']));
            flash('success', 'Demande de compte envoyée ! Un administrateur va la valider très prochainement.');
            redirect('login');
        }
    }
    render('auth/register', ['centers' => $centers, 'errors' => $errors, 'old' => $old], 'layout_auth');
}

function auth_logout(): void
{
    logout_user();
    redirect('login');
}

function auth_profile(): void
{
    $u = require_login();
    if (is_post()) {
        $action = input('action');
        if ($action === 'notifications') {
            $prefs = [];
            foreach (NOTIFY_EVENTS as $k => $ev) {
                $prefs[$k] = isset($_POST['events'][$k]);
            }
            update('users', ['notify_email' => input('notify_email') === '1' ? 1 : 0, 'notify_prefs' => json_encode($prefs)], 'id = ?', [$u['id']]);
            flash('success', 'Préférences de notification enregistrées.');
        } elseif ($action === '2fa_start') {
            $_SESSION['2fa_new'] = base32_encode(random_bytes(20));
            redirect('profile', ['_' => 'security']);
        } elseif ($action === '2fa_enable') {
            $secret = (string)($_SESSION['2fa_new'] ?? '');
            if ($secret !== '' && ($slot = totp_match($secret, (string)input('code', ''))) !== null) {
                update('users', ['totp_secret' => encrypt_secret($secret), 'totp_last' => (string)$slot], 'id = ?', [$u['id']]);
                unset($_SESSION['2fa_new']);
                audit('Double authentification activée', 'user', (int)$u['id']);
                flash('success', 'Double authentification activée : un code vous sera demandé à chaque connexion.');
            } else {
                flash('error', 'Code incorrect : vérifiez l\'heure de votre téléphone et réessayez.');
            }
            redirect('profile', ['_' => 'security']);
        } elseif ($action === '2fa_disable') {
            if (admin_2fa_required() && is_admin($u)) {
                flash('error', 'La double authentification est obligatoire pour les administrateurs.');
            } elseif (password_verify((string)($_POST['current'] ?? ''), $u['password_hash']) && user_totp_verify($u, (string)input('code', ''))) {
                update('users', ['totp_secret' => null, 'totp_last' => null], 'id = ?', [$u['id']]);
                audit('Double authentification désactivée', 'user', (int)$u['id']);
                flash('success', 'Double authentification désactivée.');
            } else {
                flash('error', 'Mot de passe ou code incorrect.');
            }
            redirect('profile', ['_' => 'security']);
        } elseif ($action === 'password') {
            $current = (string)($_POST['current'] ?? '');
            $new = (string)($_POST['new'] ?? '');
            if (!password_verify($current, $u['password_hash'])) {
                flash('error', 'Mot de passe actuel incorrect.');
            } elseif (mb_strlen($new) < 8) {
                flash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            } elseif ($new !== (string)($_POST['confirm'] ?? '')) {
                flash('error', 'La confirmation ne correspond pas.');
            } else {
                update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
                flash('success', 'Mot de passe modifié.');
            }
        } else {
            update('users', [
                'first_name' => (string)input('first_name') ?: $u['first_name'],
                'last_name' => (string)input('last_name') ?: $u['last_name'],
                'phone' => (string)input('phone'),
                'job' => (string)input('job'),
            ], 'id = ?', [$u['id']]);
            flash('success', 'Profil mis à jour.');
        }
        redirect('profile');
    }
    render('user/profile', ['u' => $u, 'title' => 'Mon profil']);
}

// ---------------------------------------------------------------- Mot de passe oublié

function auth_forgot(): void
{
    $sent = false;
    if (is_post()) {
        $email = mb_strtolower(trim((string)input('email')));
        // Limite : 3 demandes par 15 minutes et par adresse IP
        $recent = (int)val("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email LIKE 'reset:%' AND created_at >= ?", [client_ip(), date('Y-m-d H:i:s', time() - 900)]);
        insert('login_attempts', ['email' => 'reset:' . mb_substr($email, 0, 180), 'ip' => client_ip(), 'success' => 0, 'created_at' => now()]);
        $u = ($recent < 3 && mail_case_enabled('password_reset')) ? one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]) : null;
        if ($u) {
            $token = password_reset_create($u);
            $link = url('reset', ['token' => $token]);
            send_mail($u['email'], 'Réinitialisation de votre mot de passe', mail_template($u, 'Réinitialisation de votre mot de passe',
                "Vous avez demandé à réinitialiser votre mot de passe. Ce lien est valable une heure.\nSi vous n'êtes pas à l'origine de cette demande, ignorez simplement ce message.", $link));
            audit('Mot de passe oublié', 'user', (int)$u['id']);
        }
        $sent = true; // même message que le compte existe ou non
    }
    render('auth/forgot', ['sent' => $sent, 'mailOn' => mail_case_enabled('password_reset')], 'layout_auth');
}

function auth_reset(): void
{
    $token = (string)input('token', '');
    $reset = password_reset_find($token);
    $error = null;
    if ($reset && is_post()) {
        $p1 = (string)($_POST['password'] ?? '');
        if (mb_strlen($p1) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($p1 !== (string)($_POST['confirm'] ?? '')) {
            $error = 'La confirmation ne correspond pas.';
        } else {
            update('users', ['password_hash' => password_hash($p1, PASSWORD_DEFAULT)], 'id = ?', [$reset['user_id']]);
            update('password_resets', ['used_at' => now()], 'id = ?', [$reset['id']]);
            q('DELETE FROM login_attempts WHERE email = ? AND success = 0', [$reset['email']]);
            audit('Mot de passe réinitialisé', 'user', (int)$reset['user_id']);
            flash('success', input('invite') === '1' ? 'Mot de passe enregistré : connectez-vous avec votre e-mail.' : 'Mot de passe modifié. Vous pouvez vous connecter.');
            redirect('login');
        }
    }
    render('auth/reset', ['reset' => $reset, 'token' => $token, 'error' => $error], 'layout_auth');
}
