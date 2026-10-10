<?php
declare(strict_types=1);

/**
 * Centre d'assistance NLapps : conversations en direct avec les utilisateurs des applications NLapps.
 * Licences, abonnements, versions, vidéos et FAQ sont gérés par la console de chaque application (Centriva : centriva.fr/console.php).
 */
require __DIR__ . '/lib.php';

// Anciens liens de paiement : le paiement se fait désormais depuis l'application (Centriva : Paramètres → Abonnement)
if (isset($_GET['pay'])) {
    $pc = preg_match('/^[a-f0-9]{40}$/', (string)$_GET['pay']) ? hone('SELECT app FROM clients WHERE billing_token = ?', [(string)$_GET['pay']]) : null;
    if ($pc && ($cu = hub_app_console((string)$pc['app']))) {
        header('Location: ' . preg_replace('#/console\.php$#', '', $cu) . '/?paiement=' . rawurlencode((string)$_GET['pay']), true, 302);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Abonnement</title><body style="font-family:system-ui;padding:2rem;max-width:520px;margin:auto">'
        . '<h1>Abonnement</h1><p>Le règlement de l\'abonnement se fait maintenant directement dans votre application : <b>Paramètres → Abonnement</b> (administrateur).</p></body>');
}

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

function conv_list(string $tab = 'open', string $app = ''): array
{
    $where = match ($tab) {
        'pending' => "c.status = 'pending'",
        'closed' => "c.status = 'closed'",
        'all' => '1 = 1',
        default => "c.status = 'open'",
    };
    return hall("SELECT c.id, c.user_name, c.center, c.status, c.unread, c.updated_at, c.rating, cl.name AS client, cl.app,
                   (SELECT body FROM messages m WHERE m.conversation_id = c.id AND m.sender IN ('user', 'agent') ORDER BY m.id DESC LIMIT 1) AS last
                 FROM conversations c JOIN clients cl ON cl.id = c.client_id WHERE $where" . ($app !== '' ? ' AND cl.app = ' . hdb()->quote($app) : '') . "
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
    $resp['list'] = array_map(fn($r) => $r + ['app_name' => hub_app_name((string)$r['app']), 'app_color' => hub_app((string)$r['app'])['color'] ?? '#94a3b8'],
        conv_list((string)($_GET['tab'] ?? 'open'), (string)($_GET['app'] ?? '')));
    json_out($resp);
}

// ---------------------------------------------------------------- Pages

$st = hub_status();
$p = (string)($_GET['p'] ?? '');
$isAdmin = $hubUser['role'] === 'admin';
// Pages propres à chaque application (sous-menu de l'application) et pages générales
$generalPages = ['' => 'Conversations', 'access' => 'Accès au chat', 'apps' => 'Applications', 'users' => 'Comptes', 'settings' => 'Réglages', 'update' => 'Mise à jour', 'account' => 'Mon compte'];
$adminOnly = ['access', 'apps', 'users', 'update'];
if (!isset($generalPages[$p]) || (in_array($p, $adminOnly, true) && !$isAdmin)) {
    $p = '';
}
$flashMsg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$title = $generalPages[$p];
page_head($title);
$unread = (int)(hone("SELECT COALESCE(SUM(unread),0) n FROM conversations WHERE status <> 'closed'")['n'] ?? 0);
$link = fn(string $page) => 'index.php' . ($page !== '' ? '?p=' . $page : '');
$auto = hsetting('availability_mode', 'manual') === 'auto';
?>
<div class="shell">
<header class="mbar">
  <button type="button" class="burger" data-menu aria-label="Menu">☰</button>
  <a class="brand" href="index.php"><img src="assets/nlapps-mark.svg" alt="" width="26" height="26"> Assistance <b>NLapps</b></a>
  <span class="spacer"></span>
  <a href="index.php" class="mbar-unread" data-unread-pill <?= $unread ? '' : 'hidden' ?>>💬 <span data-unread><?= $unread ?></span></a>
</header>
<aside class="side" data-side>
  <a class="brand" href="index.php"><img src="assets/nlapps-mark.svg" alt="" width="30" height="30"> Assistance <b>NLapps</b></a>
  <form method="post" class="avail">
    <?= csrf_input() ?><input type="hidden" name="action" value="availability"><input type="hidden" name="back" value="<?= h($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
    <button name="online" value="<?= $st['online'] ? '0' : '1' ?>" class="pill <?= $st['online'] ? 'on' : 'off' ?> <?= $auto ? 'auto' : '' ?>" title="<?= $auto ? 'Mode horaires : cliquez pour passer en mode manuel' : 'Changer de disponibilité' ?>"><?= $st['online'] ? '● Disponible' : '○ Absent' ?><?= $auto ? ' · horaires' : '' ?></button>
  </form>
  <nav>
    <div class="nav-title">Support</div>
    <a href="index.php" class="<?= $p === '' ? 'act' : '' ?>">💬 Conversations <i class="badge" data-unread <?= $unread ? '' : 'hidden' ?>><?= $unread ?></i></a>
    <?php if ($isAdmin): ?><a href="<?= $link('access') ?>" class="<?= $p === 'access' ? 'act' : '' ?>">🔑 Accès au chat</a><?php endif; ?>
    <div class="nav-title">Applications</div>
    <?php foreach (hub_apps() as $a): ?>
      <?php if (!empty($a['console_url'])): ?><a href="<?= h($a['console_url']) ?>" target="_blank" rel="noopener" title="Clients, abonnements, versions, vidéos et FAQ : console de l'application"><span class="dot" style="background:<?= h($a['color']) ?>"></span> <?= h($a['name']) ?> ↗</a>
      <?php else: ?><span class="navtext"><span class="dot" style="background:<?= h($a['color']) ?>"></span> <?= h($a['name']) ?></span><?php endif; ?>
    <?php endforeach; ?>
    <?php if ($isAdmin): ?><a href="<?= $link('apps') ?>" class="sub <?= $p === 'apps' ? 'act' : '' ?>">＋ Gérer les applications</a><?php endif; ?>
    <div class="nav-title">Administration</div>
    <?php if ($isAdmin): ?><a href="<?= $link('users') ?>" class="<?= $p === 'users' ? 'act' : '' ?>">👥 Comptes</a><?php endif; ?>
    <a href="<?= $link('settings') ?>" class="<?= $p === 'settings' ? 'act' : '' ?>">⚙️ Réglages</a>
    <?php if ($isAdmin): ?><a href="<?= $link('update') ?>" class="<?= $p === 'update' ? 'act' : '' ?>">⬆️ Mise à jour <small>v<?= h(hub_version()) ?></small></a><?php endif; ?>
  </nav>
  <div class="side-foot">
    <a href="<?= $link('account') ?>" class="me <?= $p === 'account' ? 'act' : '' ?>"><span class="avatar"><?= h(mb_strtoupper(mb_substr($hubUser['name'], 0, 1))) ?></span><span><b><?= h($hubUser['name']) ?></b><small><?= h($hubUser['username']) ?></small></span></a>
    <button type="button" class="pill install" data-install hidden title="Installer la console comme une application">⬇ Installer</button>
    <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="logout"><button class="logout" title="Se déconnecter">⏻</button></form>
  </div>
</aside>
<div class="side-backdrop" data-menu></div>
<div class="content">
<?php
$flashHtml = $flashMsg ? '<div class="flash ' . ($flashMsg['err'] ? 'err' : '') . '">' . h($flashMsg['msg']) . '</div>' : '';
require __DIR__ . '/views/' . ($p === '' ? 'inbox' : $p) . '.php';
?>
</div>
</div>
<script>window.HUB = <?= json_encode(['csrf' => $csrf, 'vapid' => (function () { try { return hub_vapid()['public']; } catch (Throwable) { return null; } })()]) ?>;</script>
<script src="assets/hub.js?v=6"></script>
<?php
page_foot();

function page_head(string $title): void
{
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · Assistance NLapps</title>
<link rel="icon" href="assets/nlapps-mark.svg"><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/icon-192.png">
<meta name="theme-color" content="#1e1b4b"><meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="assets/hub.css?v=6"></head><body><?php
}

function page_foot(): void
{
    echo '</body></html>';
}
