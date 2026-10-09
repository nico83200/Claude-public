<?php
declare(strict_types=1);

/** Console NLapps : conversations mutualisées, puis pour chaque application gérée (sous-menu) : parc clients et licences, versions, FAQ, vidéos. */
require __DIR__ . '/lib.php';

// Page de paiement d'un client (lien personnel, sans connexion à la console)
if (isset($_GET['pay'])) {
    require HUB . '/views/pay.php';
    exit;
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
$appPages = ['clients' => 'Parc clients', 'releases' => 'Versions', 'faq' => 'FAQ', 'videos' => 'Vidéos'];
$generalPages = ['' => 'Conversations', 'billing' => 'Abonnements', 'apps' => 'Applications', 'users' => 'Comptes', 'settings' => 'Réglages', 'update' => 'Mise à jour', 'account' => 'Mon compte'];
$adminOnly = ['clients', 'releases', 'apps', 'users', 'update', 'billing'];
if (!isset($appPages[$p]) && !isset($generalPages[$p])) {
    $p = '';
}
if (in_array($p, $adminOnly, true) && !$isAdmin) {
    $p = '';
}
// Application courante : paramètre « app », sinon la dernière consultée (utile après un enregistrement de formulaire)
$app = hub_app((string)($_GET['app'] ?? '')) ?? (isset($appPages[$p]) ? (hub_app((string)($_SESSION['app'] ?? '')) ?? (hub_apps()[0] ?? null)) : null);
if ($app) {
    $_SESSION['app'] = $app['slug'];
}
if (isset($appPages[$p]) && !$app) {
    $p = 'apps';
}
$flashMsg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$title = isset($appPages[$p]) ? $appPages[$p] . ' · ' . $app['name'] : $generalPages[$p];
page_head($title);
$unread = (int)(hone("SELECT COALESCE(SUM(unread),0) n FROM conversations WHERE status <> 'closed'")['n'] ?? 0);
$link = fn(string $page, ?string $slug = null) => 'index.php' . ($page !== '' ? '?p=' . $page . ($slug ? '&app=' . rawurlencode($slug) : '') : '');
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
    <div class="nav-title">Applications</div>
    <?php foreach (hub_apps() as $a): $cur = $app && $app['slug'] === $a['slug'] && isset($appPages[$p]); ?>
      <details class="appnav" <?= $cur || count(hub_apps()) <= 3 ? 'open' : '' ?>>
        <summary><span class="dot" style="background:<?= h($a['color']) ?>"></span><?= h($a['name']) ?></summary>
        <?php foreach ($appPages as $k => $label): if (in_array($k, $adminOnly, true) && !$isAdmin) continue; ?>
          <a href="<?= $link($k, $a['slug']) ?>" class="<?= $cur && $p === $k ? 'act' : '' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </details>
    <?php endforeach; ?>
    <?php if ($isAdmin): ?><a href="<?= $link('apps') ?>" class="sub <?= $p === 'apps' ? 'act' : '' ?>">＋ Gérer les applications</a><?php endif; ?>
    <div class="nav-title">Administration</div>
    <?php if ($isAdmin): ?><a href="<?= $link('billing') ?>" class="<?= $p === 'billing' ? 'act' : '' ?>">💳 Abonnements</a><?php endif; ?>
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
<script src="assets/hub.js?v=5"></script>
<?php
page_foot();

function page_head(string $title): void
{
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · Assistance NLapps</title>
<link rel="icon" href="assets/nlapps-mark.svg"><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/icon-192.png">
<meta name="theme-color" content="#1e1b4b"><meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="assets/hub.css?v=5"></head><body><?php
}

function page_foot(): void
{
    echo '</body></html>';
}
