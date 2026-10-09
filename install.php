<?php
declare(strict_types=1);

/**
 * Assistant d'installation : crée les tables, le premier administrateur
 * et (optionnellement) des données de démonstration.
 * À supprimer ou à protéger après l'installation.
 */
define('ROOT', __DIR__);
define('APP', __DIR__ . '/app');

// Plusieurs clients installés : chaque espace se crée depuis la console NLapps, jamais par cet assistant
if (is_file(ROOT . '/instances/registry.php')) {
    http_response_code(403);
    exit('<!doctype html><meta charset="utf-8"><title>Installation</title><p style="font-family:system-ui;padding:2rem">Approvia est installé en mode multi-clients : créez les espaces depuis <a href="console.php">la console NLapps</a>.</p>');
}

$step = 'config';
$error = null;
$done = false;

/** Prérequis de l'hébergement : [libellé, ok, bloquant]. */
function install_checks(): array
{
    $w = fn(string $d) => is_dir(ROOT . "/$d") ? is_writable(ROOT . "/$d") : is_writable(ROOT);
    return [
        ['PHP 8.1 ou plus récent (actuel : ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), true],
        ['Extension pdo_mysql (base MySQL/MariaDB)', extension_loaded('pdo_mysql'), true],
        ['Extension mbstring', extension_loaded('mbstring'), true],
        ['Extension gd (photos et logo)', extension_loaded('gd'), false],
        ['Extension zip (mises à jour depuis l\'interface)', class_exists('ZipArchive'), false],
        ['Extension sodium ou openssl (chiffrement de la clé IA et du mot de passe SMTP)', function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt'), false],
        ['Extension curl (assistant IA)', extension_loaded('curl'), false],
        ['Dossier vendor/ présent (assistant IA)', is_file(ROOT . '/vendor/autoload.php'), false],
        ['Dossier de l\'application accessible en écriture (config.php)', is_writable(ROOT) || is_file(ROOT . '/config.php'), true],
        ['Dossier storage/ accessible en écriture', $w('storage'), true],
        ['Dossier uploads/ accessible en écriture', $w('uploads'), true],
    ];
}

// Étape 1 : saisie des accès à la base, test de connexion puis écriture de config.php
if (!is_file(ROOT . '/config.php') && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['db_name'])) {
    $db = [
        'driver' => 'mysql',
        'host'   => trim((string)($_POST['db_host'] ?? '')) ?: 'localhost',
        'port'   => (int)($_POST['db_port'] ?? 3306) ?: 3306,
        'name'   => trim((string)$_POST['db_name']),
        'user'   => trim((string)($_POST['db_user'] ?? '')),
        'pass'   => (string)($_POST['db_pass'] ?? ''),
    ];
    $appName = trim((string)($_POST['app_name'] ?? '')) ?: 'Approvia';
    try {
        $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
            $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $pdo = null;
        $config = "<?php\n// Généré par install.php le " . date('d/m/Y H:i') . "\nreturn " . var_export([
            'db' => $db,
            'app_name' => $appName,
            'timezone' => 'Europe/Paris',
            'anthropic_api_key' => '',
            'max_upload' => 4 * 1024 * 1024,
        ], true) . ";\n";
        foreach (['storage', 'uploads/products', 'uploads/brand'] as $d) {
            @mkdir(ROOT . "/$d", 0755, true);
        }
        if (@file_put_contents(ROOT . '/config.php', $config, LOCK_EX) === false) {
            $error = 'Impossible d\'écrire config.php : vérifiez les droits d\'écriture du dossier (755).';
        } else {
            @chmod(ROOT . '/config.php', 0640);
            header('Location: install.php');
            exit;
        }
    } catch (Throwable $e) {
        $error = 'Connexion à la base impossible : ' . $e->getMessage();
    }
}

$hasConfig = is_file(ROOT . '/config.php');

if ($hasConfig) {
    $GLOBALS['config'] = require ROOT . '/config.php';
    date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Europe/Paris');
    require APP . '/db.php';
    require APP . '/helpers.php';
    require APP . '/auth.php';
    require APP . '/domain.php';
    require APP . '/schema.php';
    require APP . '/stock.php';
    require APP . '/notify.php';
    require APP . '/features.php';
    if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
        session_start();
    }
    try {
        db();
        $step = 'admin';
        $installed = false;
        try {
            $installed = (int)val('SELECT COUNT(*) FROM users WHERE role = ?', ['admin']) > 0;
        } catch (Throwable) {
        }
        if ($installed) {
            $step = 'installed';
        }
    } catch (Throwable $e) {
        $error = 'Connexion à la base impossible : ' . $e->getMessage();
    }
}

if ($step === 'admin' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $first = trim((string)($_POST['first_name'] ?? ''));
    $last = trim((string)($_POST['last_name'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($pass) < 8 || $first === '' || $last === '') {
        $error = 'Renseignez un e-mail valide, un nom, un prénom et un mot de passe de 8 caractères minimum.';
    } else {
        try {
            schema_install();
            require APP . '/install_demo.php';
            tx(function () use ($email, $pass, $first, $last) {
                $adminId = insert('users', [
                    'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                    'first_name' => $first, 'last_name' => $last, 'job' => 'Achats',
                    'role' => 'admin', 'status' => 'active', 'created_at' => now(),
                ]);
                install_base_data();
                if (!empty($_POST['demo'])) {
                    install_demo_data($adminId);
                }
            });
            $done = true;
            $step = 'installed';
            // Sécurité : l'assistant se supprime de lui-même une fois l'installation faite
            $selfDeleted = @unlink(__FILE__);
        } catch (Throwable $e) {
            $error = 'Erreur pendant l\'installation : ' . $e->getMessage();
        }
    }
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body>
<div class="auth-main" style="min-height:100vh">
  <div class="auth-card" style="max-width:560px">
    <h1>Installation</h1>
    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>

    <?php if ($step === 'config'): ?>
      <?php $checks = install_checks(); $blocking = array_filter($checks, fn($c) => !$c[1] && $c[2]); ?>
      <div class="card card-body">
        <h3 style="margin-top:0">Vérification de l'hébergement</h3>
        <ul style="list-style:none;padding:0;margin:0">
          <?php foreach ($checks as [$label, $ok, $required]): ?>
            <li style="padding:.2rem 0"><?= $ok ? '✅' : ($required ? '❌' : '⚠️') ?> <?= h($label) ?><?= !$ok && !$required ? ' <span class="muted">(facultatif)</span>' : '' ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ($blocking): ?><p class="flash flash-error mt-1">Corrigez les points ❌ (version PHP ou extensions dans le panneau de l'hébergeur), puis rechargez la page.</p><?php endif; ?>
      </div>
      <form method="post" class="card card-body mt-1">
        <h3 style="margin-top:0">Base de données MySQL / MariaDB</h3>
        <p class="muted">Créez d'abord la base et son utilisateur chez votre hébergeur (Hostinger : <em>hPanel → Bases de données → Gestion</em>), puis recopiez les informations ici.</p>
        <div class="form-grid">
          <div class="field"><label>Nom de la base</label><input type="text" name="db_name" required placeholder="u123456789_commandes" value="<?= h($_POST['db_name'] ?? '') ?>"></div>
          <div class="field"><label>Utilisateur</label><input type="text" name="db_user" required placeholder="u123456789_achats" value="<?= h($_POST['db_user'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>Mot de passe de la base</label><input type="password" name="db_pass" autocomplete="new-password"></div>
        <div class="form-grid">
          <div class="field"><label>Serveur</label><input type="text" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>"></div>
          <div class="field"><label>Port</label><input type="number" name="db_port" value="<?= h((string)($_POST['db_port'] ?? '3306')) ?>"></div>
        </div>
        <div class="field"><label>Nom de l'application</label><input type="text" name="app_name" value="<?= h($_POST['app_name'] ?? 'Approvia') ?>"></div>
        <button class="btn btn-primary btn-lg mt-1" type="submit"<?= $blocking ? ' disabled' : '' ?>>Tester la connexion et continuer</button>
      </form>
    <?php elseif ($step === 'installed'): ?>
      <div class="card card-body">
        <?php if ($done): ?><div class="flash flash-success">Installation terminée !</div><?php endif; ?>
        <?php if (!empty($selfDeleted)): ?>
          <p>L'application est installée. Le fichier <code>install.php</code> a été supprimé automatiquement.</p>
        <?php else: ?>
          <p>L'application est installée. <strong>Pour la sécurité, supprimez le fichier <code>install.php</code></strong> du serveur.</p>
        <?php endif; ?>
        <a class="btn btn-primary" href="index.php">Ouvrir l'application</a>
      </div>
    <?php else: ?>
      <form method="post" class="card card-body">
        <p class="muted">Base connectée (<?= h(db_driver()) ?>). Créez le compte administrateur du service achats.</p>
        <div class="form-grid">
          <div class="field"><label>Prénom</label><input type="text" name="first_name" required value="<?= h($_POST['first_name'] ?? '') ?>"></div>
          <div class="field"><label>Nom</label><input type="text" name="last_name" required value="<?= h($_POST['last_name'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>E-mail</label><input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label>Mot de passe (8 caractères min.)</label><input type="password" name="password" minlength="8" required></div>
        <label class="check"><input type="checkbox" name="demo" value="1"> Charger des données de démonstration pour essayer (centres, fournisseurs, articles, demandes fictifs)</label>
        <button class="btn btn-primary btn-lg mt-1" type="submit">Installer</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body></html>
