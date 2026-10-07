<?php
declare(strict_types=1);

/**
 * Centre d'assistance NLapps : conversations en direct avec les utilisateurs des applications clientes.
 * Base SQLite dans data/ (aucune configuration de base de données nécessaire).
 */

define('HUB', __DIR__);
$GLOBALS['hub_config'] = (is_file(HUB . '/config.php') ? require HUB . '/config.php' : []) + [
    'operator_name' => 'NLapps', 'notify_email' => 'contact@nlapps.fr', 'from_email' => 'assistance@nlapps.fr',
    'ntfy_url' => '', 'timezone' => 'Europe/Paris', 'db_path' => null,
];
// Base SQLite dans data/ (protégé par .htaccess) sous un nom aléatoire, pour rester introuvable même sans .htaccess
if (!$GLOBALS['hub_config']['db_path']) {
    $nameFile = HUB . '/data/dbname.php';
    if (!is_file($nameFile)) {
        @mkdir(HUB . '/data', 0750, true);
        file_put_contents($nameFile, "<?php return 'hub-" . bin2hex(random_bytes(12)) . ".sqlite';\n", LOCK_EX);
    }
    $GLOBALS['hub_config']['db_path'] = HUB . '/data/' . (require $nameFile);
}
date_default_timezone_set($GLOBALS['hub_config']['timezone']);

function hcfg(string $k): mixed
{
    return $GLOBALS['hub_config'][$k] ?? null;
}

function hdb(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $path = (string)hcfg('db_path');
    @mkdir(dirname($path), 0750, true);
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, key_hash TEXT NOT NULL UNIQUE,
        key_hint TEXT, site TEXT, active INTEGER NOT NULL DEFAULT 1, last_seen TEXT, created_at TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
        token TEXT NOT NULL, user_name TEXT, user_email TEXT, user_role TEXT, center TEXT, context TEXT, status TEXT NOT NULL DEFAULT 'open',
        unread INTEGER NOT NULL DEFAULT 0, notified_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
        sender TEXT NOT NULL, body TEXT NOT NULL, created_at TEXT NOT NULL)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS messages_conv ON messages(conversation_id, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS conv_status ON conversations(status, updated_at)");
    return $pdo;
}

function hq(string $sql, array $p = []): PDOStatement
{
    $st = hdb()->prepare($sql);
    $st->execute(array_values($p));
    return $st;
}

function hone(string $sql, array $p = []): ?array
{
    $r = hq($sql, $p)->fetch();
    return $r ?: null;
}

function hall(string $sql, array $p = []): array
{
    return hq($sql, $p)->fetchAll();
}

function hnow(): string
{
    return date('Y-m-d H:i:s');
}

function hsetting(string $k, ?string $default = null): ?string
{
    $r = hone('SELECT v FROM settings WHERE k = ?', [$k]);
    return $r ? $r['v'] : $default;
}

function hset(string $k, ?string $v): void
{
    hq('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$k, $v]);
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Disponibilité affichée aux utilisateurs. */
function hub_status(): array
{
    return [
        'online' => hsetting('online', '1') === '1',
        'operator' => (string)hcfg('operator_name'),
        'away_message' => hsetting('away_message', 'Nous ne sommes pas disponibles pour le moment : laissez votre message, nous vous répondons dès que possible. Vous serez prévenu(e) dans l\'application.'),
    ];
}

/** Alerte de l'opérateur : e-mail et, si configuré, notification sur le téléphone (ntfy). Une alerte au plus toutes les 3 minutes par conversation. */
function hub_alert(array $conv, string $title, string $text): void
{
    if ($conv['notified_at'] && strtotime($conv['notified_at']) > time() - 180) {
        return;
    }
    hq('UPDATE conversations SET notified_at = ? WHERE id = ?', [hnow(), $conv['id']]);
    $link = hub_base_url() . '?c=' . $conv['id'];
    if ($to = (string)hcfg('notify_email')) {
        $headers = "From: Assistance NLapps <" . hcfg('from_email') . ">\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($to, '=?UTF-8?B?' . base64_encode($title) . '?=', $text . "\n\nRépondre : " . $link, $headers);
    }
    if ($ntfy = (string)hcfg('ntfy_url')) {
        // Titre et lien passés en paramètres d'URL (gère les accents, contrairement aux en-têtes HTTP)
        $url = $ntfy . (str_contains($ntfy, '?') ? '&' : '?') . http_build_query(['title' => $title, 'click' => $link, 'tags' => 'speech_balloon']);
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 4, 'ignore_errors' => true,
            'header' => 'Content-Type: text/plain; charset=UTF-8', 'content' => mb_substr($text, 0, 1000)]]);
        @file_get_contents($url, false, $ctx);
    }
}

function hub_base_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/index.php';
}

/** Création d'une clé d'accès pour une application cliente (montrée une seule fois). */
function hub_create_client(string $name, string $site = ''): string
{
    $key = 'nlh_' . bin2hex(random_bytes(20));
    hq('INSERT INTO clients (name, key_hash, key_hint, site, created_at) VALUES (?, ?, ?, ?, ?)',
        [mb_substr($name, 0, 120), hash('sha256', $key), substr($key, 0, 8) . '…' . substr($key, -4), mb_substr($site, 0, 200), hnow()]);
    return $key;
}

function hub_client_from_key(string $key): ?array
{
    if ($key === '') {
        return null;
    }
    return hone('SELECT * FROM clients WHERE key_hash = ? AND active = 1', [hash('sha256', $key)]);
}

function hub_messages(int $convId, int $after = 0): array
{
    return array_map(fn($m) => ['id' => (int)$m['id'], 'from' => $m['sender'], 'text' => $m['body'], 'at' => $m['created_at']],
        hall('SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id', [$convId, $after]));
}

function hub_add_message(int $convId, string $sender, string $body): int
{
    hq('INSERT INTO messages (conversation_id, sender, body, created_at) VALUES (?, ?, ?, ?)', [$convId, $sender, mb_substr($body, 0, 4000), hnow()]);
    $id = (int)hdb()->lastInsertId();
    if ($sender === 'user') {
        hq("UPDATE conversations SET updated_at = ?, unread = unread + 1, status = 'open' WHERE id = ?", [hnow(), $convId]);
    } else {
        hq('UPDATE conversations SET updated_at = ? WHERE id = ?', [hnow(), $convId]);
    }
    return $id;
}
