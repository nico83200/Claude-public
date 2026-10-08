<?php
/**
 * Relais entre le chat du site NLapps et le centre d'assistance NLapps (api.php).
 * Voir INTEGRATION.md : la clé nlh_… et le jeton de conversation restent ici,
 * le navigateur ne reçoit que les messages filtrés (user / agent / system).
 *
 * Configuration (au choix) :
 *  - variables d'environnement NLAPPS_SUPPORT_KEY et NLAPPS_SUPPORT_API ;
 *  - fichier PHP placé HORS de la racine web : ../../nlapps-support-config.php
 *    (modèle : support/config.sample.php).
 *
 * Le site vitrine n'a pas de comptes : le visiteur est identifié par sa session PHP.
 */
declare(strict_types=1);

const TIMEOUT = 8;
const MAX_OPEN_PER_HOUR = 5;
const MAX_SEND_PER_10MIN = 40;

/* ---------- Configuration ---------- */
$cfg = ['key' => getenv('NLAPPS_SUPPORT_KEY') ?: '', 'api' => getenv('NLAPPS_SUPPORT_API') ?: '', 'app' => 'site-nlapps'];
$cfgFile = dirname(__DIR__, 2) . '/nlapps-support-config.php';
if (is_file($cfgFile)) {
    $cfg = array_merge($cfg, array_filter((array) require $cfgFile));
}
if ($cfg['api'] === '') {
    $cfg['api'] = 'https://nlapps.fr/assistance/api.php';
}

/* ---------- Réponses ---------- */
function out(int $code, array $data): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(int $code, string $msg, array $extra = []): void
{
    out($code, ['error' => $msg] + $extra);
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if ($cfg['key'] === '') {
    fail(503, "L'assistance en direct n'est pas configurée.", ['unavailable' => true]);
}

/* ---------- Session (cookie sécurisé, même site) ---------- */
session_name('nlapps_chat');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
$conv = $_SESSION['nlh_conv'] ?? null;        // conversation en cours : ['id' => int, 'token' => string]
$rateConv = $_SESSION['nlh_rate'] ?? null;    // conversation close en attente de note
$visitor = $_SESSION['nlh_user'] ?? [];
session_write_close();                         // ne bloque pas les appels parallèles (poll / send)

function session_update(callable $fn): void
{
    session_start();
    $fn();
    session_write_close();
}

/* ---------- Appel du centre d'assistance ---------- */
function api(string $action, string $method = 'GET', array $params = [], ?array $body = null, bool $raw = false): array
{
    global $cfg;
    $url = $cfg['api'] . '?' . http_build_query(['a' => $action] + $params);
    $ch = curl_init($url);
    $headers = ['X-Api-Key: ' . $cfg['key'], 'Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => TIMEOUT, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => false,
    ];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? new stdClass(), JSON_UNESCAPED_UNICODE);
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($res === false || $code === 0) {
        return ['code' => 0, 'data' => ['error' => "Le centre d'assistance ne répond pas. Réessayez dans un instant."]];
    }
    if ($raw) {
        return ['code' => $code, 'raw' => $res, 'type' => $type];
    }
    $data = json_decode((string) $res, true);
    return ['code' => $code, 'data' => is_array($data) ? $data : ['error' => 'Réponse inattendue du centre d\'assistance.']];
}

/** Traduit une erreur de l'API pour le navigateur (sans jamais exposer clé ni jeton). */
function relay_error(array $r): void
{
    $code = $r['code'];
    $msg = $r['data']['error'] ?? 'Erreur du centre d\'assistance.';
    if ($code === 401) {
        error_log('[nlapps-chat] Clé API refusée par le centre d\'assistance (401).');
        fail(503, "L'assistance en direct est momentanément indisponible.", ['unavailable' => true]);
    }
    if ($code === 404) {
        session_update(function () { unset($_SESSION['nlh_conv']); });
        fail(404, 'Cette conversation est terminée. Votre prochain message en ouvrira une nouvelle.', ['reset' => true]);
    }
    if ($code === 422) {
        fail(422, $msg);
    }
    fail(502, $code === 0 ? $msg : "L'assistance en direct est momentanément indisponible.");
}

/** Ne garde que ce que l'utilisateur doit voir. */
function visible(array $messages): array
{
    $out = [];
    foreach ($messages as $m) {
        if (!in_array($m['from'] ?? '', ['user', 'agent', 'system'], true)) {
            continue;
        }
        $out[] = [
            'id' => (int) ($m['id'] ?? 0), 'from' => $m['from'], 'text' => (string) ($m['text'] ?? ''),
            'at' => (string) ($m['at'] ?? ''), 'file' => $m['file'] ?? null, 'author' => $m['author'] ?? null,
        ];
    }
    return $out;
}

function str(mixed $v, int $max): string
{
    $s = trim(is_scalar($v) ? (string) $v : '');
    return mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '', 0, $max);
}

/** Limites simples par session, contre les abus du formulaire public. */
function throttle(string $name, int $max, int $window): void
{
    $ok = true;
    session_update(function () use ($name, $max, $window, &$ok) {
        $now = time();
        $hits = array_filter($_SESSION['nlh_hits'][$name] ?? [], fn ($t) => $t > $now - $window);
        if (count($hits) >= $max) {
            $ok = false;
            return;
        }
        $hits[] = $now;
        $_SESSION['nlh_hits'][$name] = array_values($hits);
    });
    if (!$ok) {
        fail(429, 'Trop de messages en peu de temps. Merci de patienter quelques minutes.');
    }
}

/* ---------- Image jointe (GET, retransmise au navigateur) ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET' && ($_GET['action'] ?? '') === 'file') {
    $f = (string) ($_GET['f'] ?? '');
    $c = $conv ?: $rateConv;
    if (!$c || !preg_match('/^[A-Za-z0-9._-]{1,120}$/', $f)) {
        fail(404, 'Image introuvable.');
    }
    $r = api('file', 'GET', ['id' => $c['id'], 'token' => $c['token'], 'f' => $f], null, true);
    if ($r['code'] !== 200 || !preg_match('#^image/(jpeg|png|webp)#', $r['type'] ?? '')) {
        fail(404, 'Image introuvable.');
    }
    header('Content-Type: ' . $r['type']);
    header('Cache-Control: private, max-age=3600');
    header('Content-Security-Policy: default-src \'none\'');
    echo $r['raw'];
    exit;
}

/* ---------- Actions (POST JSON) ---------- */
if ($method !== 'POST') {
    fail(405, 'Méthode non autorisée.');
}
// Les appels doivent venir des pages du site lui-même.
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    fail(403, 'Origine non autorisée.');
}
$in = json_decode((string) file_get_contents('php://input', false, null, 0, 6_000_000), true);
if (!is_array($in)) {
    fail(400, 'Requête invalide.');
}
$action = (string) ($in['action'] ?? '');

switch ($action) {
    case 'status':
        $r = api('status');
        if ($r['code'] !== 200) {
            relay_error($r);
        }
        out(200, ['availability' => $r['data'], 'active' => (bool) $conv, 'awaitingRating' => (bool) $rateConv]);

    case 'open':
        if ($conv) {
            fail(409, 'Une conversation est déjà ouverte.', ['active' => true]);
        }
        $message = str($in['message'] ?? '', 4000);
        if ($message === '') {
            fail(422, 'Votre message est vide.');
        }
        throttle('open', MAX_OPEN_PER_HOUR, 3600);
        $user = [
            'name' => str($in['user']['name'] ?? ($visitor['name'] ?? ''), 120),
            'email' => str($in['user']['email'] ?? ($visitor['email'] ?? ''), 160),
            'center' => str($in['user']['company'] ?? ($visitor['center'] ?? ''), 160),
            'role' => 'Visiteur du site',
        ];
        if ($user['email'] !== '' && !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $user['email'] = '';
        }
        $ua = str($_SERVER['HTTP_USER_AGENT'] ?? '', 200);
        $context = str('Site vitrine NLapps · page : ' . str($in['page'] ?? '', 200) . ' · navigateur : ' . $ua, 2000);
        $transcript = [];
        foreach (array_slice((array) ($in['transcript'] ?? []), -12) as $t) {
            $from = ($t['from'] ?? '') === 'user' ? 'user' : 'bot';
            $text = str($t['text'] ?? '', 1000);
            if ($text !== '') {
                $transcript[] = ['from' => $from, 'text' => $text];
            }
        }
        $r = api('open', 'POST', [], ['user' => array_filter($user), 'message' => $message, 'context' => $context, 'transcript' => $transcript]);
        if ($r['code'] !== 200 || empty($r['data']['id']) || empty($r['data']['token'])) {
            relay_error($r);
        }
        session_update(function () use ($r, $user) {
            $_SESSION['nlh_conv'] = ['id' => (int) $r['data']['id'], 'token' => (string) $r['data']['token']];
            $_SESSION['nlh_user'] = ['name' => $user['name'], 'email' => $user['email'], 'center' => $user['center']];
            unset($_SESSION['nlh_rate']);
        });
        out(200, ['status' => 'open', 'availability' => $r['data']['status'] ?? null, 'messages' => visible($r['data']['messages'] ?? [])]);

    case 'send':
        if (!$conv) {
            fail(404, 'Aucune conversation ouverte.', ['reset' => true]);
        }
        $text = str($in['text'] ?? '', 4000);
        if ($text === '') {
            fail(422, 'Votre message est vide.');
        }
        throttle('send', MAX_SEND_PER_10MIN, 600);
        $r = api('send', 'POST', [], ['id' => $conv['id'], 'token' => $conv['token'], 'text' => $text]);
        if ($r['code'] !== 200) {
            relay_error($r);
        }
        out(200, ['ok' => true, 'id' => (int) ($r['data']['id'] ?? 0)]);

    case 'poll':
        if (!$conv) {
            fail(404, 'Aucune conversation ouverte.', ['reset' => true]);
        }
        $r = api('poll', 'GET', ['id' => $conv['id'], 'token' => $conv['token'], 'after' => max(0, (int) ($in['after'] ?? 0))]);
        if ($r['code'] !== 200) {
            relay_error($r);
        }
        $status = (string) ($r['data']['status'] ?? 'open');
        $rated = (bool) ($r['data']['rated'] ?? false);
        if ($status === 'closed') {
            // Conversation résolue : on l'oublie, en la gardant juste le temps de la note.
            session_update(function () use ($conv, $rated) {
                unset($_SESSION['nlh_conv']);
                if ($rated) {
                    unset($_SESSION['nlh_rate']);
                } else {
                    $_SESSION['nlh_rate'] = $conv;
                }
            });
        }
        out(200, ['status' => $status, 'rated' => $rated, 'availability' => $r['data']['availability'] ?? null, 'messages' => visible($r['data']['messages'] ?? [])]);

    case 'close':
        if (!$conv) {
            out(200, ['ok' => true]);
        }
        $r = api('close', 'POST', [], ['id' => $conv['id'], 'token' => $conv['token']]);
        if ($r['code'] !== 200 && $r['code'] !== 404) {
            relay_error($r);
        }
        session_update(function () use ($conv) {
            unset($_SESSION['nlh_conv']);
            $_SESSION['nlh_rate'] = $conv;
        });
        out(200, ['ok' => true]);

    case 'attach':
        if (!$conv) {
            fail(404, 'Aucune conversation ouverte.', ['reset' => true]);
        }
        throttle('send', MAX_SEND_PER_10MIN, 600);
        $data = (string) ($in['data'] ?? '');
        $bin = base64_decode($data, true);
        if ($bin === false || strlen($bin) === 0 || strlen($bin) > 4 * 1024 * 1024) {
            fail(422, 'Image refusée : JPEG, PNG ou WebP de 4 Mo maximum.');
        }
        $info = @getimagesizefromstring($bin);
        if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            fail(422, 'Image refusée : JPEG, PNG ou WebP de 4 Mo maximum.');
        }
        $r = api('attach', 'POST', [], ['id' => $conv['id'], 'token' => $conv['token'], 'data' => $data, 'text' => str($in['text'] ?? '', 500)]);
        if ($r['code'] !== 200) {
            relay_error($r);
        }
        out(200, ['ok' => true, 'id' => (int) ($r['data']['id'] ?? 0)]);

    case 'rate':
        $c = $rateConv;
        $rating = (int) ($in['rating'] ?? 0);
        if (!$c || $rating < 1 || $rating > 5) {
            fail(422, 'Note invalide.');
        }
        $r = api('rate', 'POST', [], ['id' => $c['id'], 'token' => $c['token'], 'rating' => $rating, 'comment' => str($in['comment'] ?? '', 1000)]);
        session_update(function () { unset($_SESSION['nlh_rate']); });
        if ($r['code'] !== 200 && $r['code'] !== 404) {
            relay_error($r);
        }
        out(200, ['ok' => true]);

    default:
        fail(400, 'Action inconnue.');
}
