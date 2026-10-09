<?php
declare(strict_types=1);

/**
 * Console NLapps : gestion des clients Centriva servis par ce même code.
 * Chaque client a sa base de données, ses fichiers, ses comptes et sa licence ; il est reconnu à son adresse.
 *
 * Accès : https://votre-serveur/console.php — mot de passe propre à la console (créé au premier accès
 * avec le code d'installation déposé dans storage/console-code.txt, lisible uniquement par FTP / gestionnaire de fichiers).
 */
define('NL_CONSOLE', true);
require __DIR__ . '/app/bootstrap.php';
require APP . '/instances_admin.php';

session_name('nlconsole');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

@mkdir(ROOT . '/storage', 0750, true);
const CONSOLE_AUTH = ROOT . '/storage/console-auth.json';
const CONSOLE_CODE = ROOT . '/storage/console-code.txt';
const CONSOLE_ATTEMPTS = ROOT . '/storage/console-attempts.json';

function console_auth(): array
{
    return is_file(CONSOLE_AUTH) ? (json_decode((string)file_get_contents(CONSOLE_AUTH), true) ?: []) : [];
}

function console_flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function console_go(string $page = '', array $q = []): never
{
    header('Location: console.php' . ($page || $q ? '?' . http_build_query(['p' => $page ?: null] + $q) : ''));
    exit;
}

/** Limitation des essais de mot de passe : 5 échecs par adresse IP et par quart d'heure. */
function console_throttled(bool $failed = false): bool
{
    $ip = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $all = is_file(CONSOLE_ATTEMPTS) ? (json_decode((string)file_get_contents(CONSOLE_ATTEMPTS), true) ?: []) : [];
    $all = array_map(fn($l) => array_values(array_filter($l, fn($t) => $t > time() - 900)), $all);
    if ($failed) {
        $all[$ip][] = time();
        file_put_contents(CONSOLE_ATTEMPTS, json_encode(array_filter($all)), LOCK_EX);
    }
    return count($all[$ip] ?? []) >= 5;
}

$auth = console_auth();
$logged = !empty($_SESSION['console_ok']) && ($_SESSION['console_ok'] === ($auth['hash'] ?? null));
$page = (string)($_GET['p'] ?? '');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($post) {
    csrf_check();
}
$error = null;

// ------------------------------------------------------------- Premier accès et connexion
if (!$auth) {
    if (!is_file(CONSOLE_CODE)) {
        file_put_contents(CONSOLE_CODE, strtoupper(bin2hex(random_bytes(4))) . "\n", LOCK_EX);
        @chmod(CONSOLE_CODE, 0600);
    }
    if ($post) {
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $pw = (string)($_POST['password'] ?? '');
        if (console_throttled()) {
            $error = 'Trop d\'essais : réessayez dans un quart d\'heure.';
        } elseif (!hash_equals(trim((string)file_get_contents(CONSOLE_CODE)), $code)) {
            console_throttled(true);
            $error = 'Code d\'installation incorrect.';
        } elseif (mb_strlen($pw) < 12 || $pw !== (string)($_POST['confirm'] ?? '')) {
            $error = 'Mot de passe : 12 caractères minimum, saisi deux fois à l\'identique.';
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            file_put_contents(CONSOLE_AUTH, json_encode(['hash' => $hash, 'created_at' => date('Y-m-d H:i:s')]), LOCK_EX);
            @chmod(CONSOLE_AUTH, 0600);
            @unlink(CONSOLE_CODE);
            session_regenerate_id(true);
            $_SESSION['console_ok'] = $hash;
            console_go();
        }
    }
    $page = 'setup';
} elseif (!$logged) {
    if ($post && $page === 'login') {
        if (console_throttled()) {
            $error = 'Trop d\'essais : réessayez dans un quart d\'heure.';
        } elseif (password_verify((string)($_POST['password'] ?? ''), (string)$auth['hash'])) {
            session_regenerate_id(true);
            $_SESSION['console_ok'] = $auth['hash'];
            console_go();
        } else {
            console_throttled(true);
            $error = 'Mot de passe incorrect.';
        }
    }
    $page = 'login';
} elseif ($page === 'logout') {
    $_SESSION = [];
    session_regenerate_id(true);
    console_go();
}

// ------------------------------------------------------------- Actions (connecté)
if ($logged && $post) {
    $action = (string)($_POST['action'] ?? '');
    $slug = (string)($_POST['slug'] ?? '');
    try {
        switch ($action) {
            case 'create':
                $s = instance_create([
                    'slug' => $_POST['slug'] ?? '', 'name' => $_POST['name'] ?? '', 'hosts' => $_POST['hosts'] ?? '', 'app_name' => $_POST['app_name'] ?? '',
                    'admin_first_name' => $_POST['admin_first_name'] ?? '', 'admin_last_name' => $_POST['admin_last_name'] ?? '',
                    'admin_email' => $_POST['admin_email'] ?? '', 'admin_password' => $_POST['admin_password'] ?? '',
                    'demo' => !empty($_POST['demo']), 'public_demo' => !empty($_POST['public_demo']), 'hub_url' => $_POST['hub_url'] ?? '', 'hub_key' => $_POST['hub_key'] ?? '',
                    'db' => ['driver' => $_POST['db_driver'] ?? 'sqlite', 'host' => $_POST['db_host'] ?? '', 'port' => $_POST['db_port'] ?? '',
                        'name' => $_POST['db_name'] ?? '', 'user' => $_POST['db_user'] ?? '', 'pass' => $_POST['db_pass'] ?? ''],
                ]);
                console_flash('ok', 'Espace « ' . instances_registry()[$s]['name'] . ' » créé. L\'administrateur peut se connecter à https://' . instances_registry()[$s]['hosts'][0] . '/');
                console_go();

            case 'adopt':
                $s = instance_adopt_current((string)($_POST['slug'] ?? ''), trim((string)($_POST['name'] ?? '')), (string)($_POST['hosts'] ?? ''));
                console_flash('ok', 'L\'installation existante est devenue l\'espace « ' . $s . ' » : comptes, commandes et réglages conservés.');
                console_go();

            case 'hosts':
                $reg = instances_registry();
                if (!isset($reg[$slug])) {
                    throw new RuntimeException('Client inconnu.');
                }
                $reg[$slug]['hosts'] = instance_parse_hosts((string)($_POST['hosts'] ?? ''), $slug);
                $reg[$slug]['name'] = trim((string)($_POST['name'] ?? '')) ?: $reg[$slug]['name'];
                instances_save($reg);
                console_flash('ok', 'Espace « ' . $reg[$slug]['name'] . ' » mis à jour.');
                console_go();

            case 'suspend':
            case 'resume':
                $reg = instances_registry();
                if (!isset($reg[$slug])) {
                    throw new RuntimeException('Client inconnu.');
                }
                $reg[$slug]['suspended'] = $action === 'suspend';
                instances_save($reg);
                console_flash('ok', $action === 'suspend' ? 'Espace « ' . $reg[$slug]['name'] . ' » suspendu : plus personne ne peut s\'y connecter.' : 'Espace « ' . $reg[$slug]['name'] . ' » rétabli.');
                console_go();

            case 'demo_toggle':
                $reg = instances_registry();
                if (!isset($reg[$slug])) {
                    throw new RuntimeException('Client inconnu.');
                }
                $on = empty($reg[$slug]['public_demo']);
                if ($on && empty($reg[$slug]['demo'])) {
                    throw new RuntimeException('Seul un espace créé avec les données de démonstration peut devenir une démo publique : ses données seraient effacées chaque nuit.');
                }
                $reg[$slug]['public_demo'] = $on;
                instances_save($reg);
                if ($on) {
                    instance_demo_reset($slug);
                }
                console_flash('ok', $on ? 'Démo publique activée : connexion en un clic, remise à zéro chaque nuit à 3 h.' : 'Démo publique désactivée.');
                console_go();

            case 'demo_reset':
                instance_demo_reset($slug);
                console_flash('ok', 'Démo « ' . instances_registry()[$slug]['name'] . ' » remise à zéro.');
                console_go();

            case 'delete':
                if (trim((string)($_POST['confirm'] ?? '')) !== $slug) {
                    throw new RuntimeException('Pour supprimer, saisissez exactement l\'identifiant du client (' . $slug . ').');
                }
                $archive = instance_delete($slug);
                console_flash('ok', 'Espace supprimé. Archive (base et fichiers) : storage/clients-supprimes/' . $archive . '. Une base MySQL dédiée reste à supprimer chez l\'hébergeur.');
                console_go();

            case 'update':
                $f = $_FILES['package'] ?? null;
                if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Choisissez le paquet de mise à jour (centriva-x.y.z.zip).');
                }
                $tmp = ROOT . '/storage/console-update.zip';
                move_uploaded_file($f['tmp_name'], $tmp);
                $info = instances_update_code($tmp);
                @unlink($tmp);
                console_flash('ok', 'Code mis à jour en version ' . $info['version'] . ' (sauvegarde : ' . $info['code_backup'] . ', bases sauvegardées dans chaque espace).');
                console_go('migrate'); // nouvelle requête : la migration s'exécute avec le nouveau code

            case 'migrate':
                $res = instances_migrate_all();
                $ko = array_filter($res, fn($r) => $r !== 'ok');
                console_flash($ko ? 'error' : 'ok', $ko ? 'Migration en échec : ' . implode(' · ', array_map(fn($k, $v) => "$k : $v", array_keys($ko), $ko)) : plural(count($res), 'base migrée', 'bases migrées') . ' en version ' . APP_VERSION . '.');
                console_go();

            case 'cron':
                $res = instances_cron_all(!empty($_POST['force']));
                console_flash('ok', 'Tâches planifiées : ' . implode(' · ', array_map(fn($k, $v) => "$k : $v", array_keys($res), $res)));
                console_go();

            case 'password':
                if (!password_verify((string)($_POST['current'] ?? ''), (string)$auth['hash'])) {
                    throw new RuntimeException('Mot de passe actuel incorrect.');
                }
                $pw = (string)($_POST['new'] ?? '');
                if (mb_strlen($pw) < 12 || $pw !== (string)($_POST['confirm'] ?? '')) {
                    throw new RuntimeException('Nouveau mot de passe : 12 caractères minimum, saisi deux fois à l\'identique.');
                }
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                file_put_contents(CONSOLE_AUTH, json_encode(['hash' => $hash, 'created_at' => $auth['created_at'] ?? date('Y-m-d H:i:s'), 'changed_at' => date('Y-m-d H:i:s')]), LOCK_EX);
                $_SESSION['console_ok'] = $hash;
                console_flash('ok', 'Mot de passe de la console modifié.');
                console_go();
        }
    } catch (Throwable $e) {
        console_flash('error', $e->getMessage());
        $_SESSION['form'] = array_diff_key($_POST, array_flip(['admin_password', 'db_pass', 'password', 'current', 'new', 'confirm', '_token']));
        console_go($action === 'create' ? 'new' : ($action === 'adopt' ? 'adopt' : ''), $action === 'hosts' ? ['edit' => $slug] : []);
    }
}

// Migration automatique après une mise à jour du code
if ($logged && $page === 'migrate') {
    $res = instances_migrate_all();
    $ko = array_filter($res, fn($r) => $r !== 'ok');
    console_flash($ko ? 'error' : 'ok', $ko ? 'Migration en échec : ' . implode(' · ', array_map(fn($k, $v) => "$k : $v", array_keys($ko), $ko)) : plural(count($res), 'base migrée', 'bases migrées') . ' en version ' . APP_VERSION . '.');
    console_go();
}

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$form = $_SESSION['form'] ?? [];
unset($_SESSION['form']);
$f = fn(string $k, string $d = '') => e($form[$k] ?? $d);
$registry = $logged ? instances_registry() : [];
$single = is_file(ROOT . '/config.php');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Console NLapps · Centriva</title>
<style>
:root { --bg:#f4f6fb; --card:#fff; --text:#1e1b4b; --muted:#64748b; --border:#e2e8f0; --primary:#4f46e5; --soft:#eef2ff; --green:#059669; --red:#dc2626; --amber:#d97706; }
@media (prefers-color-scheme: dark) { :root { --bg:#0e1122; --card:#171b33; --text:#e2e8f0; --muted:#94a3b8; --border:#2a3055; --soft:#22285a; } }
* { box-sizing: border-box; }
body { margin:0; font-family: Inter, system-ui, -apple-system, sans-serif; background:var(--bg); color:var(--text); line-height:1.45; }
header { background:linear-gradient(135deg,#312e81,#4f46e5); color:#fff; padding:1rem 1.25rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap; }
header b { font-size:1.1rem; } header nav { display:flex; gap:.25rem; flex-wrap:wrap; margin-left:auto; }
header nav a { color:#fff; text-decoration:none; padding:.4rem .75rem; border-radius:8px; font-size:.92rem; } header nav a.on, header nav a:hover { background:rgba(255,255,255,.18); }
main { max-width:1100px; margin:0 auto; padding:1.25rem 1rem 3rem; }
h1 { font-size:1.45rem; margin:.5rem 0 1rem; } h2 { font-size:1.1rem; margin:0 0 .75rem; }
.card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.1rem 1.2rem; margin-bottom:1rem; box-shadow:0 2px 8px rgba(30,27,75,.05); }
.muted { color:var(--muted); } small { font-size:.85rem; }
label { display:block; font-weight:600; font-size:.88rem; margin:.7rem 0 .25rem; }
input, select, textarea { width:100%; padding:.6rem .7rem; border:1px solid var(--border); border-radius:9px; font:inherit; background:var(--card); color:var(--text); }
.check { display:flex; gap:.5rem; align-items:center; font-weight:500; } .check input { width:auto; }
.grid2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 1rem; }
@media (max-width:640px) { .grid2 { grid-template-columns:1fr; } }
.btn { display:inline-flex; align-items:center; gap:.35rem; padding:.55rem 1rem; border-radius:9px; border:1px solid var(--border); background:var(--card); color:var(--text); font:inherit; font-weight:600; cursor:pointer; text-decoration:none; }
.btn.primary { background:var(--primary); border-color:var(--primary); color:#fff; } .btn.danger { color:var(--red); } .btn.sm { padding:.35rem .7rem; font-size:.85rem; }
.flash { padding:.75rem 1rem; border-radius:10px; margin-bottom:1rem; } .flash.ok { background:#ecfdf5; color:#065f46; } .flash.error { background:#fef2f2; color:#991b1b; }
@media (prefers-color-scheme: dark) { .flash.ok { background:#064e3b; color:#d1fae5; } .flash.error { background:#7f1d1d; color:#fee2e2; } }
.tag { display:inline-block; padding:.12rem .55rem; border-radius:999px; font-size:.75rem; font-weight:700; background:var(--soft); color:var(--primary); }
.tag.green { background:#d1fae5; color:var(--green); } .tag.red { background:#fee2e2; color:var(--red); } .tag.amber { background:#fef3c7; color:var(--amber); }
.clients { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:1rem; }
.client h2 { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin-bottom:.25rem; }
.kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; margin:.75rem 0; text-align:center; }
.kpis div { background:var(--soft); border-radius:10px; padding:.45rem .2rem; } .kpis b { display:block; font-size:1.15rem; } .kpis span { font-size:.72rem; color:var(--muted); }
.row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; } .row form { margin:0; }
details summary { cursor:pointer; font-weight:600; font-size:.9rem; margin-top:.5rem; }
code { background:var(--soft); padding:.1rem .35rem; border-radius:6px; font-size:.85rem; word-break:break-all; }
.auth { max-width:420px; margin:3rem auto; }
</style>
</head>
<body>
<header>
  <b>Centriva · Console NLapps</b><small style="opacity:.8">v<?= e(APP_VERSION) ?></small>
  <?php if ($logged): ?>
  <nav>
    <a class="<?= $page === '' ? 'on' : '' ?>" href="console.php">Clients</a>
    <a class="<?= $page === 'new' ? 'on' : '' ?>" href="console.php?p=new">Nouveau client</a>
    <a class="<?= $page === 'update' ? 'on' : '' ?>" href="console.php?p=update">Mise à jour</a>
    <a class="<?= $page === 'account' ? 'on' : '' ?>" href="console.php?p=account">Sécurité</a>
    <a href="console.php?p=logout">Déconnexion</a>
  </nav>
  <?php endif; ?>
</header>
<main>
<?php foreach ($flash as [$t, $m]): ?><div class="flash <?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
<?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>

<?php if ($page === 'setup'): ?>
  <div class="card auth">
    <h1>Première connexion</h1>
    <p class="muted">Pour prouver que vous gérez ce serveur, ouvrez le fichier <code>storage/console-code.txt</code> (FTP ou gestionnaire de fichiers de l'hébergeur) et recopiez le code qu'il contient. Il sera supprimé une fois le mot de passe créé.</p>
    <form method="post"><?= csrf_field() ?>
      <label>Code d'installation</label><input name="code" required autocomplete="off" autofocus>
      <label>Mot de passe de la console <small class="muted">(12 caractères minimum)</small></label><input type="password" name="password" minlength="12" required autocomplete="new-password">
      <label>Confirmation</label><input type="password" name="confirm" minlength="12" required autocomplete="new-password">
      <p><button class="btn primary">Créer le mot de passe</button></p>
    </form>
  </div>

<?php elseif ($page === 'login'): ?>
  <div class="card auth">
    <h1>Console NLapps</h1>
    <form method="post" action="console.php?p=login"><?= csrf_field() ?>
      <label>Mot de passe</label><input type="password" name="password" required autofocus autocomplete="current-password">
      <p><button class="btn primary">Se connecter</button></p>
    </form>
  </div>

<?php elseif ($page === 'new'): ?>
  <h1>Nouveau client</h1>
  <form method="post" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h2>L'espace</h2>
    <div class="grid2">
      <div><label>Nom du client</label><input name="name" value="<?= $f('name') ?>" required placeholder="ex : Groupe Santé Var"></div>
      <div><label>Identifiant <small class="muted">(minuscules, chiffres, tirets — définitif)</small></label><input name="slug" value="<?= $f('slug') ?>" required pattern="[a-z0-9][a-z0-9\-]{0,38}[a-z0-9]?" placeholder="ex : sante-var"></div>
    </div>
    <label>Adresse(s) de l'espace <small class="muted">(une par ligne ; à faire pointer vers ce dossier chez l'hébergeur)</small></label>
    <textarea name="hosts" rows="2" required placeholder="sante-var.centriva.fr"><?= $f('hosts') ?></textarea>
    <label>Nom affiché de l'application</label><input name="app_name" value="<?= $f('app_name', 'Centriva') ?>">

    <h2 style="margin-top:1.4rem">Base de données</h2>
    <label class="check"><input type="radio" name="db_driver" value="sqlite" <?= ($form['db_driver'] ?? 'sqlite') === 'sqlite' ? 'checked' : '' ?>> SQLite : un fichier propre au client, rien à créer chez l'hébergeur (jusqu'à quelques dizaines d'utilisateurs)</label>
    <label class="check"><input type="radio" name="db_driver" value="mysql" <?= ($form['db_driver'] ?? '') === 'mysql' ? 'checked' : '' ?>> MySQL / MariaDB : une base vide, créée pour ce client chez l'hébergeur (recommandé au-delà)</label>
    <div class="grid2">
      <div><label>Serveur</label><input name="db_host" value="<?= $f('db_host', 'localhost') ?>"></div>
      <div><label>Port</label><input name="db_port" value="<?= $f('db_port', '3306') ?>"></div>
      <div><label>Nom de la base</label><input name="db_name" value="<?= $f('db_name') ?>"></div>
      <div><label>Utilisateur</label><input name="db_user" value="<?= $f('db_user') ?>"></div>
      <div><label>Mot de passe</label><input type="password" name="db_pass" autocomplete="new-password"></div>
    </div>

    <h2 style="margin-top:1.4rem">Premier administrateur du client</h2>
    <div class="grid2">
      <div><label>Prénom</label><input name="admin_first_name" value="<?= $f('admin_first_name') ?>" required></div>
      <div><label>Nom</label><input name="admin_last_name" value="<?= $f('admin_last_name') ?>" required></div>
      <div><label>E-mail (identifiant)</label><input type="email" name="admin_email" value="<?= $f('admin_email') ?>" required></div>
      <div><label>Mot de passe provisoire <small class="muted">(10 caractères min., à transmettre au client)</small></label><input name="admin_password" minlength="10" required value="<?= e(substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(12))), 0, 12)) ?>"></div>
    </div>

    <h2 style="margin-top:1.4rem">Licence et assistance NLapps</h2>
    <p class="muted" style="margin:0"><small>Clé de l'installation créée dans le parc clients du centre d'assistance (licence, conversations, vidéos, mises à jour). Facultatif : elle peut aussi être collée plus tard dans Paramètres → Assistance.</small></p>
    <div class="grid2">
      <div><label>Adresse de l'API</label><input name="hub_url" value="<?= $f('hub_url', 'https://nlapps.fr/assistance/api.php') ?>"></div>
      <div><label>Clé</label><input name="hub_key" value="<?= $f('hub_key') ?>" placeholder="nlh_…"></div>
    </div>
    <label class="check" style="margin-top:1rem"><input type="checkbox" name="demo" value="1" <?= !empty($form['demo']) ? 'checked' : '' ?>> Remplir avec les données de démonstration (centres, fournisseurs, articles et comptes fictifs)</label>
    <label class="check"><input type="checkbox" name="public_demo" value="1" <?= !empty($form['public_demo']) ? 'checked' : '' ?>> Démo publique pour vos prospects : connexion en un clic avec chaque rôle, données remises à zéro chaque nuit, paramètres et e-mails désactivés</label>
    <p><button class="btn primary">Créer l'espace</button></p>
  </form>

<?php elseif ($page === 'adopt' && $single): ?>
  <h1>Reprendre l'installation actuelle</h1>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="adopt">
    <p class="muted">L'installation Centriva existante (config.php, base, fichiers) devient un client de la console. <strong>Sa base n'est pas modifiée</strong> : comptes, commandes, réglages et licence sont conservés. Ses fichiers sont copiés dans son espace ; l'ancienne configuration est mise de côté dans <code>storage/</code>.</p>
    <div class="grid2">
      <div><label>Nom du client</label><input name="name" value="<?= $f('name', (string)((require ROOT . '/config.php')['app_name'] ?? '')) ?>" required></div>
      <div><label>Identifiant</label><input name="slug" value="<?= $f('slug') ?>" required placeholder="ex : imss"></div>
    </div>
    <label>Adresse(s) actuelle(s) de l'installation <small class="muted">(une par ligne)</small></label>
    <textarea name="hosts" rows="2" required><?= $f('hosts', instance_normalize_host((string)($_SERVER['HTTP_HOST'] ?? ''))) ?></textarea>
    <p><button class="btn primary">Reprendre comme client</button></p>
  </form>

<?php elseif ($page === 'update'): ?>
  <h1>Mise à jour de tous les clients</h1>
  <div class="card">
    <p>Version installée : <strong><?= e(APP_VERSION) ?></strong>. Le code est commun : une mise à jour s'applique à tous les espaces en une fois.</p>
    <ol class="muted"><li>Sauvegarde de la base de chaque client (dans son espace) et du code.</li><li>Remplacement des fichiers ; en cas d'échec, l'ancienne version est remise automatiquement.</li><li>Migration de la base de chaque client.</li></ol>
    <form method="post" enctype="multipart/form-data" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="update">
      <input type="file" name="package" accept=".zip" required style="max-width:340px"><button class="btn primary">Installer pour tous les clients</button></form>
  </div>
  <div class="card">
    <h2>Bases des clients</h2>
    <p class="muted"><small>Si un espace affiche une version de base différente du code (après un incident), relancez la migration.</small></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="migrate"><button class="btn">Migrer toutes les bases en v<?= e(APP_VERSION) ?></button></form>
  </div>
  <div class="card">
    <h2>Tâches planifiées</h2>
    <p class="muted"><small>Programmez chez l'hébergeur, toutes les 5 à 15 minutes : <code>php <?= e(ROOT) ?>/cron.php</code> — il traite chaque client à son tour (e-mails, rappels, sauvegardes, licence…).</small></p>
    <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="cron"><label class="check" style="margin:0"><input type="checkbox" name="force" value="1"> tout forcer</label><button class="btn">Lancer maintenant pour tous</button></form>
  </div>

<?php elseif ($page === 'account'): ?>
  <h1>Sécurité de la console</h1>
  <form method="post" class="card" style="max-width:480px"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <label>Mot de passe actuel</label><input type="password" name="current" required autocomplete="current-password">
    <label>Nouveau mot de passe <small class="muted">(12 caractères minimum)</small></label><input type="password" name="new" minlength="12" required autocomplete="new-password">
    <label>Confirmation</label><input type="password" name="confirm" minlength="12" required autocomplete="new-password">
    <p><button class="btn primary">Changer le mot de passe</button></p>
  </form>
  <div class="card"><p class="muted" style="margin:0"><small>Mot de passe perdu : supprimez <code>storage/console-auth.json</code> par FTP ; un nouveau code d'installation sera créé au prochain accès.</small></p></div>

<?php else: ?>
  <div class="row" style="justify-content:space-between">
    <h1><?= plural(count($registry), 'client', 'clients') ?></h1>
    <div class="row"><?php if ($single): ?><a class="btn" href="console.php?p=adopt">Reprendre l'installation actuelle</a><?php endif; ?><a class="btn primary" href="console.php?p=new">+ Nouveau client</a></div>
  </div>
  <?php if (!$registry): ?>
    <div class="card"><p>Aucun client pour l'instant.</p>
      <p class="muted"><small>Chaque client aura sa base, ses fichiers, ses comptes et sa licence, à sa propre adresse (ex. <code>imss.centriva.fr</code>). Chez l'hébergeur, faites pointer chaque adresse (ou un sous-domaine générique <code>*.centriva.fr</code>) vers ce dossier.<?= $single ? ' L\'installation actuelle peut devenir le premier client sans rien perdre.' : '' ?></small></p></div>
  <?php endif; ?>
  <div class="clients">
  <?php foreach ($registry as $slug => $i): $st = instance_stats($slug); $edit = ($_GET['edit'] ?? '') === $slug; ?>
    <div class="card client">
      <h2><?= e($i['name']) ?> <?= !empty($i['suspended']) ? '<span class="tag red">suspendu</span>' : '<span class="tag green">actif</span>' ?><?= !empty($i['public_demo']) ? ' <span class="tag amber">démo publique</span>' : (!empty($i['demo']) ? ' <span class="tag amber">démo</span>' : '') ?></h2>
      <small class="muted"><?= e($slug) ?> · <?= e($st['driver'] ?? '?') ?> · base v<?= e($st['db_version'] ?? '?') ?><?= ($st['db_version'] ?? '') !== APP_VERSION ? ' <span class="tag amber">à migrer</span>' : '' ?></small>
      <div class="row" style="margin-top:.35rem"><?php foreach ((array)$i['hosts'] as $h): ?><a href="https://<?= e($h) ?>/" target="_blank" rel="noopener"><small><?= e($h) ?></small></a><?php endforeach; ?></div>
      <?php if ($st['ok']): ?>
        <div class="kpis">
          <div><b><?= (int)$st['users'] ?></b><span>comptes</span></div>
          <div><b><?= (int)$st['centers'] ?></b><span>centres</span></div>
          <div><b><?= (int)$st['products'] ?></b><span>articles</span></div>
          <div><b><?= (int)$st['orders_month'] ?></b><span>bons ce mois</span></div>
        </div>
        <?php if (!empty($i['public_demo'])): ?><small class="muted">Dernière remise à zéro : <?= !empty($st['demo_reset_at']) ? e(date('d/m/Y H:i', strtotime((string)$st['demo_reset_at']))) : '—' ?></small><br><?php endif; ?>
        <small class="muted">Licence : <?= e(['unmanaged' => 'non reliée à NLapps', 'active' => 'active', 'grace' => 'période de grâce', 'expired' => 'expirée', 'suspended' => 'suspendue'][$st['licence']] ?? ($st['licence'] ?: '—')) ?> · dernière connexion : <?= $st['last_login'] ? e(date('d/m/Y H:i', strtotime((string)$st['last_login']))) : 'jamais' ?></small>
      <?php else: ?><p class="flash error"><small><?= e($st['error']) ?></small></p><?php endif; ?>
      <div class="row" style="margin-top:.75rem">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="action" value="<?= !empty($i['suspended']) ? 'resume' : 'suspend' ?>">
          <button class="btn sm" onclick="return confirm('<?= !empty($i['suspended']) ? 'Rétablir l\\\'accès à cet espace ?' : 'Suspendre cet espace ? Plus personne ne pourra s\\\'y connecter.' ?>')"><?= !empty($i['suspended']) ? 'Rétablir' : 'Suspendre' ?></button></form>
        <?php if (!empty($i['demo'])): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="action" value="demo_toggle"><button class="btn sm"><?= !empty($i['public_demo']) ? 'Désactiver la démo publique' : 'Rendre publique' ?></button></form>
        <?php endif; ?>
        <?php if (!empty($i['public_demo'])): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="action" value="demo_reset"><button class="btn sm" onclick="return confirm('Effacer toutes les données de la démo et repartir des données de départ ?')">Remettre à zéro</button></form>
        <?php endif; ?>
      </div>
      <details <?= $edit ? 'open' : '' ?>><summary>Nom et adresses</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="hosts"><input type="hidden" name="slug" value="<?= e($slug) ?>">
          <label>Nom</label><input name="name" value="<?= e($i['name']) ?>">
          <label>Adresses (une par ligne)</label><textarea name="hosts" rows="2"><?= e(implode("\n", (array)$i['hosts'])) ?></textarea>
          <p><button class="btn sm primary">Enregistrer</button></p></form>
      </details>
      <details><summary class="muted">Supprimer l'espace</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?= e($slug) ?>">
          <p class="muted"><small>La base et les fichiers sont d'abord archivés dans <code>storage/clients-supprimes/</code>. Saisissez <b><?= e($slug) ?></b> pour confirmer.</small></p>
          <input name="confirm" autocomplete="off" placeholder="<?= e($slug) ?>"><p><button class="btn sm danger">Supprimer définitivement</button></p></form>
      </details>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>
