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

        // Anti-force brute simple : 5 essais puis temporisation
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] > 5 && (time() - ($_SESSION['login_last'] ?? 0)) < 60) {
            $error = 'Trop de tentatives. Patientez une minute avant de réessayer.';
        } else {
            $_SESSION['login_last'] = time();
            $u = one('SELECT * FROM users WHERE email = ?', [$email]);
            if (!$u || !password_verify($password, $u['password_hash'])) {
                $error = 'Identifiants incorrects.';
            } elseif ($u['status'] === 'pending') {
                $error = 'Votre compte est en attente de validation par un administrateur.';
            } elseif ($u['status'] !== 'active') {
                $error = 'Ce compte est désactivé.';
            } else {
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
                }
                unset($_SESSION['login_attempts']);
                login_user($u);
                redirect($u['role'] === 'admin' ? 'admin' : 'dashboard');
            }
        }
    }
    render('auth/login', ['error' => $error, 'email' => $email], 'layout_auth');
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
            notify(admin_ids(), 'account_pending', 'Compte à valider : ' . $old['first_name'] . ' ' . $old['last_name'],
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
