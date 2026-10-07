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
if (getenv('HUB_TEST_DB')) { // tests automatiques : base temporaire
    $GLOBALS['hub_config']['db_path'] = getenv('HUB_TEST_DB');
}
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
    hub_migrate($pdo);
    return $pdo;
}

/** Évolutions du schéma (idempotent) : licences, parc, versions publiées, FAQ partagée, réponses rapides… */
function hub_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS releases (id INTEGER PRIMARY KEY AUTOINCREMENT, app TEXT NOT NULL DEFAULT 'approvia', version TEXT NOT NULL,
        notes TEXT, file TEXT NOT NULL, sha256 TEXT NOT NULL, size INTEGER NOT NULL, published INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, UNIQUE(app, version))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS faq (id INTEGER PRIMARY KEY AUTOINCREMENT, app TEXT NOT NULL DEFAULT '*', question TEXT NOT NULL, keywords TEXT,
        answer TEXT NOT NULL, link_label TEXT, link_route TEXT, admin_only INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1,
        source_conv INTEGER, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS quick_replies (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, body TEXT NOT NULL, position INTEGER NOT NULL DEFAULT 0)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subs (id INTEGER PRIMARY KEY AUTOINCREMENT, endpoint TEXT NOT NULL UNIQUE, p256dh TEXT NOT NULL, auth TEXT NOT NULL,
        label TEXT, created_at TEXT NOT NULL, last_ok TEXT)");
    $cols = [
        'clients' => ['app' => "TEXT NOT NULL DEFAULT 'approvia'", 'plan' => "TEXT NOT NULL DEFAULT 'Abonnement'", 'status' => "TEXT NOT NULL DEFAULT 'active'",
            'paid_until' => 'TEXT', 'ai_option' => 'INTEGER NOT NULL DEFAULT 0', 'licence_note' => 'TEXT', 'app_version' => 'TEXT', 'instance_url' => 'TEXT',
            'php_version' => 'TEXT', 'stats' => 'TEXT', 'last_check' => 'TEXT', 'prev_key_hash' => 'TEXT', 'prev_key_until' => 'TEXT', 'contact_email' => 'TEXT'],
        'conversations' => ['rating' => 'INTEGER', 'rating_comment' => 'TEXT', 'transcript_sent' => 'INTEGER NOT NULL DEFAULT 0'],
        'messages' => ['file' => 'TEXT'],
    ];
    foreach ($cols as $table => $defs) {
        $have = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($defs as $col => $def) {
            if (!in_array($col, $have, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
            }
        }
    }
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

/**
 * Horaires d'ouverture : {"1":[["09:00","12:30"],["14:00","18:00"]], …} (1 = lundi … 7 = dimanche).
 * Mode « auto » : disponible pendant les horaires ; mode « manual » : bouton Disponible / Absent.
 */
function hub_schedule(): array
{
    $s = json_decode((string)hsetting('schedule', ''), true);
    if (!is_array($s)) {
        $s = [];
        foreach ([1, 2, 3, 4, 5] as $d) {
            $s[$d] = [['09:00', '12:30'], ['14:00', '18:00']];
        }
    }
    return $s;
}

function hub_in_hours(?int $ts = null): bool
{
    $ts ??= time();
    $hm = date('H:i', $ts);
    foreach (hub_schedule()[(int)date('N', $ts)] ?? [] as [$from, $to]) {
        if ($hm >= $from && $hm < $to) {
            return true;
        }
    }
    return false;
}

function hub_online(): bool
{
    return hsetting('availability_mode', 'manual') === 'auto' ? hub_in_hours() : hsetting('online', '1') === '1';
}

/** Disponibilité affichée aux utilisateurs. */
function hub_status(): array
{
    return [
        'online' => hub_online(),
        'operator' => (string)hcfg('operator_name'),
        'away_message' => hsetting('away_message', 'Nous ne sommes pas disponibles pour le moment : laissez votre message, nous vous répondons dès que possible. Vous serez prévenu(e) dans l\'application.'),
    ];
}

/** Alerte de l'opérateur pour une conversation : une alerte au plus toutes les 3 minutes par conversation. */
function hub_alert(array $conv, string $title, string $text): void
{
    if ($conv['notified_at'] && strtotime($conv['notified_at']) > time() - 180) {
        return;
    }
    hq('UPDATE conversations SET notified_at = ? WHERE id = ?', [hnow(), $conv['id']]);
    hub_notify($title, $text, hub_base_url() . '?c=' . $conv['id']);
}

/** Prévient l'opérateur : notification push (téléphone, ordinateur), e-mail et, si configuré, ntfy. */
function hub_notify(string $title, string $text, string $link): void
{
    hub_push_all($title, mb_substr($text, 0, 300), $link);
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
    $h = hash('sha256', $key);
    // Après un renouvellement, l'ancienne clé reste valable quelques jours le temps que le client colle la nouvelle
    return hone('SELECT * FROM clients WHERE active = 1 AND (key_hash = ? OR (prev_key_hash = ? AND prev_key_until >= ?))', [$h, $h, hnow()]);
}

/** Nouvelle clé pour un client (l'ancienne reste acceptée 14 jours). Renvoie la clé en clair (montrée une fois). */
function hub_rotate_key(int $clientId): string
{
    $c = hone('SELECT * FROM clients WHERE id = ?', [$clientId]);
    $key = 'nlh_' . bin2hex(random_bytes(20));
    hq('UPDATE clients SET prev_key_hash = ?, prev_key_until = ?, key_hash = ?, key_hint = ? WHERE id = ?',
        [$c['key_hash'], date('Y-m-d H:i:s', strtotime('+14 days')), hash('sha256', $key), substr($key, 0, 8) . '…' . substr($key, -4), $clientId]);
    return $key;
}

const LICENCE_GRACE_DAYS = 15;

/**
 * Licence d'un client : active (payée ou sans échéance), grace (échéance passée depuis moins de 15 jours),
 * expired, suspended (suspendue par NLapps). L'option IA n'est fournie que sous licence active ou en délai de grâce.
 */
function hub_licence(array $c): array
{
    $today = date('Y-m-d');
    $until = $c['paid_until'] ?: null;
    if ($c['status'] === 'suspended' || !(int)$c['active']) {
        $status = 'suspended';
    } elseif (!$until || $today <= $until) {
        $status = 'active';
    } elseif ($today <= date('Y-m-d', strtotime($until . ' +' . LICENCE_GRACE_DAYS . ' days'))) {
        $status = 'grace';
    } else {
        $status = 'expired';
    }
    $days = $until ? (int)floor((strtotime($until) - strtotime($today)) / 86400) : null;
    return [
        'status' => $status, 'plan' => (string)$c['plan'], 'paid_until' => $until, 'days_left' => $days,
        'ai' => (bool)(int)$c['ai_option'] && in_array($status, ['active', 'grace'], true),
        'grace_until' => $until ? date('Y-m-d', strtotime($until . ' +' . LICENCE_GRACE_DAYS . ' days')) : null,
        'message' => (string)($c['licence_note'] ?? ''),
        'contact' => ['email' => (string)hcfg('notify_email'), 'name' => (string)hcfg('operator_name')],
    ];
}

// ---------------------------------------------------------------- Versions publiées

function hub_releases_dir(): string
{
    $d = dirname((string)hcfg('db_path')) . '/releases';
    @mkdir($d, 0750, true);
    return $d;
}

/** Dernière version publiée d'une application (sans le chemin du fichier). */
function hub_latest_release(string $app): ?array
{
    $all = hall('SELECT id, app, version, notes, sha256, size, created_at FROM releases WHERE app = ? AND published = 1', [$app]);
    usort($all, fn($a, $b) => version_compare($b['version'], $a['version']));
    return $all[0] ?? null;
}

/** Lit un paquet de mise à jour (version.json ou VERSION + première section de CHANGELOG.md). */
function hub_inspect_package(string $path): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Extension PHP zip absente sur ce serveur.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Archive ZIP illisible.');
    }
    $manifest = json_decode((string)$zip->getFromName('version.json'), true) ?: [];
    $version = (string)($manifest['version'] ?? trim((string)$zip->getFromName('VERSION')));
    $notes = (string)($manifest['notes'] ?? '');
    if ($notes === '' && ($cl = $zip->getFromName('CHANGELOG.md')) && preg_match('/^##\s.*?\n(.*?)(?=^##\s|\z)/ms', $cl, $m)) {
        $notes = trim($m[1]);
    }
    $zip->close();
    if (!preg_match('/^\d+\.\d+\.\d+([.-][\w.]+)?$/', $version)) {
        throw new RuntimeException('Version introuvable dans le paquet (version.json ou VERSION).');
    }
    return ['version' => $version, 'notes' => $notes];
}

// ---------------------------------------------------------------- FAQ partagée

/** Mots-clés par défaut d'une question : mots significatifs, minuscules, sans accents. */
function hub_keywords(string $text): string
{
    $t = mb_strtolower($text);
    $t = strtr($t, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe']);
    $stop = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'a', 'au', 'aux', 'en', 'je', 'j', 'on', 'il', 'elle', 'est', 'pas', 'ne', 'que', 'qui', 'quoi',
        'comment', 'pour', 'par', 'sur', 'dans', 'avec', 'mon', 'ma', 'mes', 'ce', 'cet', 'cette', 'se', 's', 'l', 'd', 'n', 'y', 'vous', 'nous', 'faire', 'peut', 'puis', 'bonjour', 'merci'];
    $words = array_filter(preg_split('/[^a-z0-9]+/', $t), fn($w) => strlen($w) > 1 && !in_array($w, $stop, true));
    return implode(' ', array_slice(array_values(array_unique($words)), 0, 20));
}

/** FAQ d'une application, au format attendu par le chatbot des applications. */
function hub_faq_for(string $app): array
{
    $rows = hall("SELECT * FROM faq WHERE active = 1 AND (app = '*' OR app = ?) ORDER BY id", [$app]);
    return array_map(fn($f) => [
        'id' => (int)$f['id'], 'q' => $f['question'], 'k' => trim(($f['keywords'] ?: '') . ' ' . hub_keywords($f['question'])),
        'a' => $f['answer'], 'link' => $f['link_label'] && $f['link_route'] ? [$f['link_label'], $f['link_route']] : null, 'admin' => (bool)$f['admin_only'],
    ], $rows);
}

function hub_messages(int $convId, int $after = 0): array
{
    return array_map(fn($m) => ['id' => (int)$m['id'], 'from' => $m['sender'], 'text' => $m['body'], 'at' => $m['created_at'], 'file' => $m['file'] ?? null],
        hall('SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id', [$convId, $after]));
}

function hub_add_message(int $convId, string $sender, string $body, ?string $file = null): int
{
    hq('INSERT INTO messages (conversation_id, sender, body, file, created_at) VALUES (?, ?, ?, ?, ?)', [$convId, $sender, mb_substr($body, 0, 4000), $file, hnow()]);
    $id = (int)hdb()->lastInsertId();
    if ($sender === 'user') {
        hq("UPDATE conversations SET updated_at = ?, unread = unread + 1, status = 'open' WHERE id = ?", [hnow(), $convId]);
    } else {
        hq('UPDATE conversations SET updated_at = ? WHERE id = ?', [hnow(), $convId]);
    }
    return $id;
}

// ---------------------------------------------------------------- Double authentification (TOTP, applications Google Authenticator, Authy…)

function base32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $b32): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32))) as $c) {
        $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_code(string $secret, ?int $t = null): string
{
    $counter = pack('N*', 0, intdiv($t ?? time(), 30));
    $h = hash_hmac('sha1', $counter, base32_decode($secret), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Vérifie un code à 6 chiffres (tolérance ±30 s) ; un code déjà utilisé est refusé. */
function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }
    foreach ([-1, 0, 1] as $w) {
        $t = time() + $w * 30;
        if (hash_equals(totp_code($secret, $t), $code)) {
            $slot = (string)intdiv($t, 30);
            if (hsetting('totp_last') === $slot) {
                return false;
            }
            hset('totp_last', $slot);
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------- Notifications push (console installée sur le téléphone)

function b64u(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64u_dec(string $s): string
{
    return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/** Clés VAPID de la console (créées au premier besoin). Renvoie ['pem' => clé privée, 'public' => clé publique base64url]. */
function hub_vapid(): array
{
    $pem = hsetting('vapid_pem');
    if (!$pem) {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$k) {
            throw new RuntimeException('OpenSSL indisponible pour les notifications.');
        }
        openssl_pkey_export($k, $pem);
        hset('vapid_pem', $pem);
    }
    $d = openssl_pkey_get_details(openssl_pkey_get_private($pem))['ec'];
    return ['pem' => $pem, 'public' => b64u("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT))];
}

/** Signature ES256 au format JOSE (r||s) à partir d'une signature DER OpenSSL. */
function es256_sign(string $data, string $pem): string
{
    openssl_sign($data, $der, $pem, OPENSSL_ALGO_SHA256);
    $pos = 3;
    $rLen = ord($der[$pos]);
    $r = substr($der, $pos + 1, $rLen);
    $pos += 1 + $rLen + 1;
    $sLen = ord($der[$pos]);
    $s = substr($der, $pos + 1, $sLen);
    return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
}

/** Chiffrement d'un message push (RFC 8291, aes128gcm). */
function webpush_encrypt(string $payload, string $p256dh, string $authSecret): string
{
    $uaPub = b64u_dec($p256dh);
    $auth = b64u_dec($authSecret);
    $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ed = openssl_pkey_get_details($eph)['ec'];
    $asPub = "\x04" . str_pad($ed['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ed['y'], 32, "\0", STR_PAD_LEFT);
    $peerPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $uaPub), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $shared = openssl_pkey_derive(openssl_pkey_get_public($peerPem), $eph, 32);
    if ($shared === false) {
        throw new RuntimeException('Clé d\'abonnement invalide.');
    }
    $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPub . $asPub, $auth);
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(65) . $asPub . $cipher . $tag;
}

/** Envoie une notification à un abonnement. Renvoie le code HTTP (201 = reçu ; 404/410 = abonnement expiré). */
function webpush_send(array $sub, array $message): int
{
    $v = hub_vapid();
    $p = parse_url($sub['endpoint']);
    $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $jwtHead = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $jwtBody = b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . (hcfg('notify_email') ?: 'contact@nlapps.fr')]));
    $jwt = $jwtHead . '.' . $jwtBody . '.' . b64u(es256_sign($jwtHead . '.' . $jwtBody, $v['pem']));
    $body = webpush_encrypt(json_encode($message, JSON_UNESCAPED_UNICODE), $sub['p256dh'], $sub['auth']);
    $headers = ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400', 'Urgency: high',
        'Authorization: vapid t=' . $jwt . ', k=' . $v['public']];
    if (function_exists('curl_init')) {
        $ch = curl_init($sub['endpoint']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $code;
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 6, 'ignore_errors' => true]]);
    @file_get_contents($sub['endpoint'], false, $ctx);
    return preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m) ? (int)$m[1] : 0;
}

/** Notifie tous les appareils abonnés (téléphone, ordinateur). */
function hub_push_all(string $title, string $body, string $url): int
{
    $n = 0;
    foreach (hall('SELECT * FROM push_subs') as $sub) {
        try {
            $code = webpush_send($sub, ['title' => $title, 'body' => $body, 'url' => $url]);
        } catch (Throwable) {
            $code = 0;
        }
        if (in_array($code, [404, 410], true)) {
            hq('DELETE FROM push_subs WHERE id = ?', [$sub['id']]);
        } elseif ($code >= 200 && $code < 300) {
            hq('UPDATE push_subs SET last_ok = ? WHERE id = ?', [hnow(), $sub['id']]);
            $n++;
        }
    }
    return $n;
}

// ---------------------------------------------------------------- Pièces jointes (captures d'écran)

function hub_files_dir(): string
{
    $d = dirname((string)hcfg('db_path')) . '/files';
    @mkdir($d, 0750, true);
    return $d;
}

/** Enregistre une image (JPEG, PNG, WebP ; 4 Mo max), contrôlée par son contenu réel. Renvoie le nom de fichier. */
function hub_store_image(string $data): string
{
    if (strlen($data) > 4 * 1024 * 1024) {
        throw new RuntimeException('Image trop lourde (4 Mo maximum).');
    }
    $info = @getimagesizefromstring($data);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) {
        throw new RuntimeException('Format non pris en charge : envoyez une image JPEG, PNG ou WebP.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    file_put_contents(hub_files_dir() . '/' . $name, $data, LOCK_EX);
    return $name;
}

// ---------------------------------------------------------------- Transcription envoyée à l'utilisateur à la clôture

function hub_send_transcript(array $conv): bool
{
    if (!$conv['user_email'] || !filter_var($conv['user_email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $who = ['user' => $conv['user_name'] ?: 'Vous', 'agent' => hcfg('operator_name'), 'system' => '—'];
    $lines = [];
    foreach (hub_messages((int)$conv['id']) as $m) {
        if (isset($who[$m['from']])) {
            $lines[] = '[' . substr($m['at'], 0, 16) . '] ' . $who[$m['from']] . ' : ' . $m['text'] . (!empty($m['file']) ? ' (image jointe)' : '');
        }
    }
    $body = "Bonjour,\n\nVoici la transcription de votre conversation avec l'assistance " . hcfg('operator_name') . ".\n\n" . implode("\n", $lines)
        . "\n\nBesoin d'autre chose ? Répondez depuis la bulle d'aide de votre application.\n\n— " . hcfg('operator_name');
    $headers = 'From: Assistance ' . hcfg('operator_name') . ' <' . hcfg('from_email') . ">\r\nReply-To: " . hcfg('notify_email') . "\r\nContent-Type: text/plain; charset=UTF-8";
    $ok = @mail($conv['user_email'], '=?UTF-8?B?' . base64_encode('Votre conversation avec l\'assistance ' . hcfg('operator_name')) . '?=', $body, $headers);
    if ($ok) {
        hq('UPDATE conversations SET transcript_sent = 1 WHERE id = ?', [$conv['id']]);
    }
    return $ok;
}


// ---------------------------------------------------------------- Suggestion de réponse par l'IA (Claude)

function hub_ai_available(): bool
{
    if (is_file(HUB . '/vendor/autoload.php')) {
        require_once HUB . '/vendor/autoload.php';
    }
    return class_exists(\Anthropic\Client::class) && (string)hsetting('anthropic_api_key', '') !== '';
}

/**
 * Propose une réponse à la dernière question de l'utilisateur, à partir de la conversation, du contexte technique,
 * de la FAQ partagée et des réponses rapides. L'opérateur relit avant d'envoyer.
 */
function hub_ai_suggest(array $conv): string
{
    if (!hub_ai_available()) {
        throw new RuntimeException('Suggestion IA indisponible : renseignez la clé API Claude dans Réglages (et le dossier vendor/ doit être présent).');
    }
    $who = ['user' => 'Utilisateur', 'agent' => 'Conseiller', 'bot' => 'Chatbot', 'user_bot' => 'Utilisateur (au chatbot)', 'system' => 'Système'];
    $transcript = implode("\n", array_map(fn($m) => $who[$m['from']] . ' : ' . $m['text'], array_slice(hub_messages((int)$conv['id']), -30)));
    $faq = implode("\n", array_map(fn($f) => '- ' . $f['q'] . ' → ' . $f['a'], hub_faq_for((string)($conv['app'] ?? 'approvia'))));
    $quick = implode("\n", array_map(fn($r) => '- ' . $r['title'] . ' : ' . $r['body'], hall('SELECT title, body FROM quick_replies ORDER BY position, id')));
    $system = 'Tu aides le conseiller de l\'assistance ' . hcfg('operator_name') . ' à répondre aux utilisateurs de ses applications '
        . '(notamment Approvia, logiciel d\'achats et de stock pour centres de santé). Rédige UNIQUEMENT le message à envoyer à l\'utilisateur, '
        . 'en français, ton cordial et professionnel, vouvoiement, concis (2 à 6 phrases), avec des étapes concrètes si utile. '
        . 'N\'invente pas de fonction : si l\'information manque, propose de vérifier ou demande une précision. Pas de signature.'
        . ($faq ? "\n\nFAQ connue :\n" . $faq : '') . ($quick ? "\n\nRéponses types de l'équipe :\n" . $quick : '');
    $user = 'Application : ' . ($conv['client'] ?? '') . "\nContexte technique :\n" . ($conv['context'] ?: '—')
        . "\n\nConversation :\n" . $transcript . "\n\nPropose la prochaine réponse du conseiller.";
    $client = new \Anthropic\Client(apiKey: (string)hsetting('anthropic_api_key'));
    $params = [
        'model' => (string)hsetting('anthropic_model', 'claude-opus-5-5'),
        'maxTokens' => 4000,
        'system' => [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
        'messages' => [['role' => 'user', 'content' => $user]],
        'outputConfig' => ['effort' => 'low'],
        // Si le modèle décline la demande, l'API bascule automatiquement sur un modèle de repli
        'fallbacks' => 'default',
        'betas' => ['server-side-fallback-2026-07-01'],
        'requestOptions' => ['timeout' => 45, 'maxRetries' => 1],
    ];
    try {
        try {
            $message = $client->beta->messages->create(...$params);
        } catch (\Anthropic\Core\Exceptions\BadRequestException $e) {
            if (!preg_match('/fallback|beta/i', $e->getMessage())) {
                throw $e;
            }
            unset($params['fallbacks'], $params['betas']);
            $message = $client->beta->messages->create(...$params);
        }
    } catch (\Anthropic\Core\Exceptions\AuthenticationException) {
        throw new RuntimeException('Clé API Claude refusée : vérifiez-la dans Réglages.');
    } catch (\Anthropic\Core\Exceptions\RateLimitException) {
        throw new RuntimeException('Trop de demandes à l\'IA pour le moment : réessayez dans une minute.');
    } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
        throw new RuntimeException('L\'IA n\'a pas pu répondre (' . ($e->type?->value ?? 'erreur') . ').');
    } catch (\Anthropic\Core\Exceptions\APIConnectionException) {
        throw new RuntimeException('Connexion à l\'IA impossible depuis le serveur.');
    }
    if ($message->stopReason === 'refusal') {
        throw new RuntimeException('L\'IA a décliné cette demande : rédigez la réponse vous-même.');
    }
    $text = '';
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $text .= $block->text;
        }
    }
    return trim($text);
}
