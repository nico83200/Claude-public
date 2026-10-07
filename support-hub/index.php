<?php
declare(strict_types=1);

/** Console de l'opérateur NLapps : conversations, parc clients et licences, versions, FAQ partagée, réglages. */
require __DIR__ . '/lib.php';

session_name('nlapps_hub');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');

$_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($post && !hash_equals($csrf, (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''))) {
    http_response_code(419);
    exit('Session expirée : rechargez la page.');
}

const PRICE_BASE = 39;
const PRICE_AI = 15;

function csrf_input(): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

function flash(string $msg, bool $error = false): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'err' => $error];
}

function go(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------- Connexion (mot de passe puis, si activé, code à 6 chiffres)

require __DIR__ . '/views/auth.php';

// ---------------------------------------------------------------- Fichiers joints (images), réservés à l'opérateur connecté

if (isset($_GET['file'])) {
    $f = hone('SELECT file FROM messages WHERE file = ?', [basename((string)$_GET['file'])]);
    $path = $f ? hub_files_dir() . '/' . $f['file'] : '';
    if (!$f || !is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . (getimagesize($path)['mime'] ?? 'application/octet-stream'));
    header('Cache-Control: private, max-age=86400');
    readfile($path);
    exit;
}

// ---------------------------------------------------------------- Actions

if ($post) {
    require __DIR__ . '/views/actions.php';
}

// ---------------------------------------------------------------- Flux JSON pour l'actualisation en direct

function conv_list(string $tab = 'open'): array
{
    $where = match ($tab) {
        'pending' => "c.status = 'pending'",
        'closed' => "c.status = 'closed'",
        'all' => '1 = 1',
        default => "c.status = 'open'",
    };
    return hall("SELECT c.id, c.user_name, c.center, c.status, c.unread, c.updated_at, c.rating, cl.name AS client,
                   (SELECT body FROM messages m WHERE m.conversation_id = c.id AND m.sender IN ('user', 'agent') ORDER BY m.id DESC LIMIT 1) AS last
                 FROM conversations c JOIN clients cl ON cl.id = c.client_id WHERE $where
                 ORDER BY c.unread > 0 DESC, c.updated_at DESC LIMIT 80");
}

function tab_counts(): array
{
    $rows = hall('SELECT status, COUNT(*) n FROM conversations GROUP BY status');
    return array_column($rows, 'n', 'status');
}

if (isset($_GET['feed'])) {
    $c = (int)($_GET['c'] ?? 0);
    $resp = ['unread' => (int)(hone("SELECT COALESCE(SUM(unread),0) n FROM conversations WHERE status <> 'closed'")['n'] ?? 0), 'counts' => tab_counts()];
    if ($c) {
        $resp['messages'] = hub_messages($c, (int)($_GET['after'] ?? 0));
        $resp['status'] = hone('SELECT status FROM conversations WHERE id = ?', [$c])['status'] ?? null;
        hq('UPDATE conversations SET unread = 0 WHERE id = ?', [$c]);
    }
    $resp['list'] = conv_list((string)($_GET['tab'] ?? 'open'));
    json_out($resp);
}

// ---------------------------------------------------------------- Pages

$st = hub_status();
$p = (string)($_GET['p'] ?? '');
$pages = ['' => 'Conversations', 'clients' => 'Parc clients', 'releases' => 'Versions', 'faq' => 'FAQ partagée', 'settings' => 'Réglages', 'update' => 'Mise à jour'];
if (!isset($pages[$p])) {
    $p = '';
}
$flashMsg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
page_head($pages[$p]);
?>
<header class="top">
  <a class="brand" href="index.php"><img src="assets/nlapps-mark.svg" alt="" width="30" height="30"> Assistance <b>NLapps</b></a>
  <form method="post" class="avail">
    <?= csrf_input() ?><input type="hidden" name="action" value="availability"><input type="hidden" name="back" value="<?= h($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
    <?php $auto = hsetting('availability_mode', 'manual') === 'auto'; ?>
    <button name="online" value="<?= $st['online'] ? '0' : '1' ?>" class="pill <?= $st['online'] ? 'on' : 'off' ?> <?= $auto ? 'auto' : '' ?>" title="<?= $auto ? 'Mode horaires : cliquez pour passer en mode manuel' : 'Changer de disponibilité' ?>"><?= $st['online'] ? '● Disponible' : '○ Absent' ?><?= $auto ? ' · horaires' : '' ?></button>
  </form>
  <nav><?php foreach ($pages as $k => $label): if ($k === 'update') continue; ?><a href="index.php<?= $k !== '' ? '?p=' . $k : '' ?>" class="<?= $p === $k || ($k === 'settings' && $p === 'update') ? 'act' : '' ?>"><?= h($label) ?></a><?php endforeach; ?></nav>
  <button type="button" class="pill install" data-install hidden title="Installer la console comme une application">⬇ Installer</button>
</header>
<?php
$flashHtml = $flashMsg ? '<div class="flash ' . ($flashMsg['err'] ? 'err' : '') . '">' . h($flashMsg['msg']) . '</div>' : '';
require __DIR__ . '/views/' . ($p === '' ? 'inbox' : $p) . '.php';
?>
<script>window.HUB = <?= json_encode(['csrf' => $csrf, 'vapid' => (function () { try { return hub_vapid()['public']; } catch (Throwable) { return null; } })()]) ?>;</script>
<script src="assets/hub.js?v=3"></script>
<?php
page_foot();

function page_head(string $title): void
{
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · Assistance NLapps</title>
<link rel="icon" href="assets/nlapps-mark.svg"><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/icon-192.png">
<meta name="theme-color" content="#1e1b4b"><meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="assets/hub.css?v=3"></head><body><?php
}

function page_foot(): void
{
    echo '</body></html>';
}
