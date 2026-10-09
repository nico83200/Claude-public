<?php
declare(strict_types=1);

/**
 * Plateforme Centriva multi-clients : ce qui est commun à tous les clients.
 *
 *  - Comptes super administrateur (NLapps) : créent les clients, installent les mises à jour, publient les vidéos.
 *  - Connexion unique sur centriva.fr : e-mail + mot de passe ; Centriva retrouve le client du compte (chaque client garde
 *    sa propre base) et ouvre la session dans son espace, par un jeton à usage unique signé avec la clé de ce client.
 *  - Vidéos centrales : publiées une fois, visibles dans tous les clients.
 *
 * Données communes : storage/central/ (comptes super administrateur, vidéos), jamais accessible depuis le web.
 */

function central_dir(string $sub = ''): string
{
    $d = ROOT . '/storage/central';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
        @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    return $d . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
}

function central_json(string $file): array
{
    $f = central_dir($file);
    return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
}

function central_json_save(string $file, array $data): void
{
    $f = central_dir($file);
    $tmp = $f . '.' . bin2hex(random_bytes(4));
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    @chmod($tmp, 0600);
    rename($tmp, $f);
}

// ---------------------------------------------------------------- Comptes super administrateur

function superadmins(): array
{
    return central_json('superadmins.json');
}

function superadmin_find(string $email): ?array
{
    foreach (superadmins() as $a) {
        if (mb_strtolower($a['email']) === mb_strtolower(trim($email))) {
            return $a;
        }
    }
    return null;
}

function superadmin_get(string $id): ?array
{
    foreach (superadmins() as $a) {
        if ($a['id'] === $id) {
            return $a;
        }
    }
    return null;
}

/** Crée ou met à jour un compte (clé : id). */
function superadmin_save(array $a): array
{
    $all = superadmins();
    $a['email'] = mb_strtolower(trim((string)$a['email']));
    if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL) || trim((string)($a['name'] ?? '')) === '') {
        throw new RuntimeException('Nom et e-mail valide obligatoires.');
    }
    foreach ($all as $o) {
        if ($o['email'] === $a['email'] && $o['id'] !== ($a['id'] ?? '')) {
            throw new RuntimeException('Un compte super administrateur utilise déjà cet e-mail.');
        }
    }
    $a['id'] ??= bin2hex(random_bytes(6));
    $a['created_at'] ??= date('Y-m-d H:i:s');
    $found = false;
    foreach ($all as $i => $o) {
        if ($o['id'] === $a['id']) {
            $all[$i] = $a;
            $found = true;
        }
    }
    if (!$found) {
        $all[] = $a;
    }
    central_json_save('superadmins.json', array_values($all));
    return $a;
}

function superadmin_delete(string $id): void
{
    central_json_save('superadmins.json', array_values(array_filter(superadmins(), fn($a) => $a['id'] !== $id)));
}

/** Session commune (connexion sur centriva.fr et administration), limitée à la racine du site. */
function central_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('nlconsole');
    session_set_cookie_params(['lifetime' => 0, 'path' => instance_web_dir() . '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

/** Super administrateur connecté (null sinon). */
function central_superadmin(): ?array
{
    $id = (string)($_SESSION['super_id'] ?? '');
    $a = $id !== '' ? superadmin_get($id) : null;
    return $a && hash_equals((string)($_SESSION['super_hash'] ?? ''), substr(hash('sha256', $a['hash']), 0, 32)) ? $a : null;
}

function central_superadmin_login(array $a): void
{
    session_regenerate_id(true);
    $_SESSION['super_id'] = $a['id'];
    $_SESSION['super_hash'] = substr(hash('sha256', $a['hash']), 0, 32); // un changement de mot de passe ferme les autres sessions
    $a['last_login'] = date('Y-m-d H:i:s');
    superadmin_save($a);
}

/** 5 échecs par adresse IP et par quart d'heure. */
function central_throttled(bool $failed = false): bool
{
    $file = central_dir('attempts.json');
    $ip = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $all = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $all = array_map(fn($l) => array_values(array_filter((array)$l, fn($t) => $t > time() - 900)), $all);
    if ($failed) {
        $all[$ip][] = time();
        file_put_contents($file, json_encode(array_filter($all)), LOCK_EX);
    }
    return count($all[$ip] ?? []) >= 5;
}

// ---------------------------------------------------------------- Connexion unique

/**
 * Comptes clients correspondant à cet e-mail et ce mot de passe : [['slug', 'name', 'uid'], …].
 * Le mot de passe est vérifié dans la base de chaque client : seul un compte dont le mot de passe correspond est proposé.
 */
function central_find_accounts(string $email, string $password): array
{
    require_once APP . '/instances_admin.php';
    $email = mb_strtolower(trim($email));
    $out = [];
    foreach (instances_registry() as $slug => $i) {
        if (!empty($i['suspended'])) {
            continue;
        }
        try {
            $u = instance_run((string)$slug, fn() => one("SELECT id, password_hash, status FROM users WHERE email = ? AND deleted_at IS NULL", [$email]));
        } catch (Throwable) {
            continue;
        }
        if ($u && $u['status'] === 'active' && password_verify($password, (string)$u['password_hash'])) {
            $out[] = ['slug' => (string)$slug, 'name' => (string)$i['name'], 'uid' => (int)$u['id']];
        }
    }
    return $out;
}

/** Comptes clients (actifs) ayant cet e-mail, sans vérifier de mot de passe (mot de passe oublié). */
function central_find_email(string $email): array
{
    require_once APP . '/instances_admin.php';
    $out = [];
    foreach (instances_registry() as $slug => $i) {
        if (empty($i['suspended'])) {
            try {
                if (instance_run((string)$slug, fn() => val("SELECT COUNT(*) FROM users WHERE email = ? AND status = 'active' AND deleted_at IS NULL", [mb_strtolower(trim($email))]))) {
                    $out[] = (string)$slug;
                }
            } catch (Throwable) {
            }
        }
    }
    return $out;
}

/**
 * Jeton de connexion à usage unique (60 secondes) pour ouvrir la session dans l'espace du client.
 * Signé avec la clé secrète propre à ce client : il n'est valable que chez lui.
 */
function central_handoff(string $slug, int $uid): string
{
    require_once APP . '/instances_admin.php';
    return instance_run($slug, function () use ($uid) {
        $payload = rtrim(strtr(base64_encode(json_encode(['u' => $uid, 'x' => time() + 60, 'n' => bin2hex(random_bytes(8))])), '+/', '-_'), '=');
        return $payload . '.' . hash_hmac('sha256', $payload, app_secret_key());
    });
}

/** Vérifie un jeton dans l'espace du client (une seule utilisation) et renvoie l'identifiant du compte. */
function central_handoff_verify(string $token): ?int
{
    [$payload, $sig] = array_pad(explode('.', $token, 2), 2, '');
    if ($payload === '' || !hash_equals(hash_hmac('sha256', $payload, app_secret_key()), $sig)) {
        return null;
    }
    $d = json_decode((string)base64_decode(strtr($payload, '-_', '+/')), true);
    if (!is_array($d) || (int)($d['x'] ?? 0) < time()) {
        return null;
    }
    $used = array_filter(json_decode((string)setting('sso_used', '[]'), true) ?: [], fn($x) => $x > time());
    if (isset($used[$d['n']])) {
        return null;
    }
    $used[(string)$d['n']] = time() + 120;
    set_setting('sso_used', json_encode($used));
    return (int)$d['u'];
}

/** Adresse de l'espace d'un client sur ce serveur. */
function central_space_url(string $slug): string
{
    return instance_web_dir() . '/' . $slug . '/';
}

// ---------------------------------------------------------------- Vidéos centrales

function central_videos(): array
{
    return central_json('videos.json');
}

function central_videos_save(array $list): void
{
    usort($list, fn($a, $b) => [(int)$a['position'], $a['uid']] <=> [(int)$b['position'], $b['uid']]);
    central_json_save('videos.json', array_values($list));
}

function central_videos_dir(): string
{
    $d = central_dir('videos');
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

/** Vidéos centrales au format des lignes de la table « videos » d'un client (source « central »). */
function central_video_rows(): array
{
    if (!instances_enabled()) {
        return [];
    }
    $rows = [];
    foreach (central_videos() as $v) {
        if (empty($v['published'])) {
            continue;
        }
        $rows[] = [
            'id' => 0, 'uid' => (string)$v['uid'], 'source' => 'central', 'title' => (string)$v['title'], 'description' => $v['description'] ?? null,
            'keywords' => $v['keywords'] ?? null, 'chapters' => json_encode($v['chapters'] ?? [], JSON_UNESCAPED_UNICODE), 'audience' => $v['audience'] ?? 'all',
            'file' => (string)$v['file'], 'size' => (int)($v['size'] ?? 0), 'duration' => $v['duration'] ?? null, 'position' => (int)($v['position'] ?? 0) - 1000,
            'active' => 1, 'welcome' => !empty($v['welcome']) ? 1 : 0, 'created_at' => $v['created_at'] ?? null, 'updated_at' => $v['updated_at'] ?? null,
        ];
    }
    return $rows;
}
