<?php
declare(strict_types=1);

/**
 * Assistant d'installation : crée les tables, le premier administrateur
 * et (optionnellement) des données de démonstration.
 * À supprimer ou à protéger après l'installation.
 */
define('ROOT', __DIR__);
define('APP', __DIR__ . '/app');

$hasConfig = is_file(ROOT . '/config.php');
$step = 'config';
$error = null;
$done = false;

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
      <div class="card card-body">
        <p><strong>Étape 1 :</strong> copiez <code>config.sample.php</code> en <code>config.php</code> et renseignez les accès à votre base MySQL/MariaDB (ou choisissez SQLite).</p>
        <p><strong>Étape 2 :</strong> (optionnel, pour l'assistant IA) exécutez <code>composer install</code> et renseignez la clé API Anthropic.</p>
        <p>Rechargez ensuite cette page.</p>
      </div>
    <?php elseif ($step === 'installed'): ?>
      <div class="card card-body">
        <?php if ($done): ?><div class="flash flash-success">Installation terminée !</div><?php endif; ?>
        <p>L'application est installée. <strong>Pour la sécurité, supprimez le fichier <code>install.php</code></strong> du serveur.</p>
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
        <label class="check"><input type="checkbox" name="demo" value="1" checked> Charger des données de démonstration (centres, fournisseurs, articles, demandes)</label>
        <button class="btn btn-primary btn-lg mt-1" type="submit">Installer</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body></html>
