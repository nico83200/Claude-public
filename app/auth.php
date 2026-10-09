<?php
declare(strict_types=1);

function user(): ?array
{
    static $cache = [];
    $id = (int)($_SESSION['uid'] ?? 0);
    if (!$id) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = one("SELECT * FROM users WHERE id = ? AND status = 'active'", [$id]);
        if (!$cache[$id]) {
            unset($_SESSION['uid']);
        }
    }
    return $cache[$id];
}

/** Rôles du service achats : l'administrateur et l'acheteur (sans l'organisation ni les paramètres). */
const PURCHASING_ROLES = ['admin', 'buyer'];

/** Libellés des rôles, du moins au plus étendu. */
const ROLE_LABELS = ['user' => 'Salarié', 'manager' => 'Responsable de centre', 'buyer' => 'Acheteur', 'admin' => 'Administrateur'];

function role_label(?string $role): string
{
    return ROLE_LABELS[$role ?? ''] ?? 'Salarié';
}

/** Service achats (administrateur ou acheteur). */
function is_admin(?array $u = null): bool
{
    return in_array(($u ?? user())['role'] ?? '', PURCHASING_ROLES, true);
}

/** Administrateur : seul à gérer l'organisation (centres, comptes) et les paramètres. */
function is_superadmin(?array $u = null): bool
{
    return (($u ?? user())['role'] ?? '') === 'admin';
}

function require_login(): array
{
    $u = user();
    if (!$u) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            json_response(['error' => 'auth'], 401);
        }
        redirect('login');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if (!is_admin($u)) {
        abort(403);
    }
    return $u;
}

function require_superadmin(): array
{
    $u = require_login();
    if (!is_superadmin($u)) {
        abort(403, 'Réservé à l\'administrateur : l\'organisation et les paramètres ne sont pas accessibles aux acheteurs.');
    }
    return $u;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    unset($_SESSION['center_id']);
    update('users', ['last_login' => now()], 'id = ?', [$u['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Centres accessibles à l'utilisateur (un administrateur voit tout). */
function user_centers(?array $u = null): array
{
    static $cache = [];
    $u ??= user();
    if (!$u) {
        return [];
    }
    $id = (int)$u['id'];
    if (!isset($cache[$id])) {
        $cache[$id] = is_admin($u)
            ? all('SELECT * FROM centers WHERE active = 1 ORDER BY name')
            : all('SELECT c.* FROM centers c JOIN user_centers uc ON uc.center_id = c.id
                   WHERE uc.user_id = ? AND c.active = 1 ORDER BY c.name', [$id]);
    }
    return $cache[$id];
}

function can_access_center(int $centerId): bool
{
    foreach (user_centers() as $c) {
        if ((int)$c['id'] === $centerId) {
            return true;
        }
    }
    return false;
}

/** Centre courant de navigation (sélecteur de site). */
function current_center(): ?array
{
    $centers = user_centers();
    if (!$centers) {
        return null;
    }
    $wanted = isset($_GET['c']) ? (int)$_GET['c'] : (int)($_SESSION['center_id'] ?? 0);
    foreach ($centers as $c) {
        if ((int)$c['id'] === $wanted) {
            $_SESSION['center_id'] = $wanted;
            return $c;
        }
    }
    $_SESSION['center_id'] = (int)$centers[0]['id'];
    return $centers[0];
}

function require_center(): array
{
    $c = current_center();
    if (!$c) {
        render('no_center');
        exit;
    }
    return $c;
}

function job_choices(): array
{
    return ['Secrétaire', 'Médecin', 'Kinésithérapeute', 'Infirmier(e)', 'Dentiste', 'Assistant(e) dentaire', 'Sage-femme', 'Orthoptiste', 'Podologue', 'Psychologue', 'Responsable de centre', 'Agent d\'entretien', 'Direction', 'Achats', 'Autre'];
}
