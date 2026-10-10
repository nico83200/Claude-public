<?php
declare(strict_types=1);

/**
 * Assistant d'installation : vérifie l'hébergement, crée la base, le compte administrateur et config.php.
 * Se désactive automatiquement une fois l'application installée.
 */
require __DIR__ . '/app/bootstrap.php';

vs_security_headers();
Auth::startSession();

$installed = is_file(vs_config_path());
$errors = [];
$checks = [
    'PHP ≥ 8.1' => PHP_VERSION_ID >= 80100,
    'Extension PDO' => extension_loaded('pdo'),
    'Pilote SQLite ou MySQL' => extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
    'Extension mbstring' => extension_loaded('mbstring'),
    'Extension SimpleXML (taux BCE)' => extension_loaded('simplexml'),
    'Dossier data/ inscriptible' => is_writable(__DIR__ . '/data'),
    'Dossier racine inscriptible (config.php)' => is_writable(__DIR__) || $installed,
    'Assistant IA : dossier vendor/ (optionnel)' => is_file(__DIR__ . '/vendor/autoload.php'),
];

$v = fn(string $k, string $d = '') => htmlspecialchars((string)($_POST[$k] ?? $d), ENT_QUOTES);

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Session expirée, rechargez la page.';
    }
    $driver = ($_POST['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    $dbCfg = $driver === 'mysql'
        ? ['driver' => 'mysql', 'host' => trim($_POST['host'] ?? 'localhost'), 'port' => (int)($_POST['port'] ?? 3306),
           'name' => trim($_POST['name'] ?? ''), 'user' => trim($_POST['user'] ?? ''), 'pass' => (string)($_POST['pass'] ?? '')]
        : ['driver' => 'sqlite', 'path' => '__DIR__/data/voyage.sqlite'];
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse e-mail invalide.';
    }
    if (mb_strlen($password) < 10) {
        $errors[] = 'Le mot de passe doit contenir au moins 10 caractères.';
    }
    if ($password !== ($_POST['password2'] ?? '')) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    if (!$errors) {
        try {
            $runtimeCfg = $dbCfg;
            if ($driver === 'sqlite') {
                $runtimeCfg['path'] = __DIR__ . '/data/voyage.sqlite';
            }
            $db = new Database($runtimeCfg);
            $db->migrate();
            if ((int)$db->value('SELECT COUNT(*) FROM users') > 0) {
                throw new RuntimeException('Cette base contient déjà un compte : installation existante ?');
            }
            $auth = new Auth($db);
            $auth->createUser($email, $password, trim((string)($_POST['nom'] ?? '')));
            $settings = new Settings($db);
            $settings->save(['agence_nom' => trim((string)($_POST['agence'] ?? '')) ?: Settings::DEFAULTS['agence_nom']]);
            $repo = new Repository($db);
            (new Rates($repo, $db))->seedIfEmpty();
            if (!empty($_POST['demo'])) {
                require __DIR__ . '/app/Demo.php';
                Demo::seed($repo, $db);
            }

            $export = var_export([
                'db' => $dbCfg,
                'ai' => ['api_key' => trim((string)($_POST['ai_key'] ?? '')), 'model' => AiAssistant::DEFAULT_MODEL,
                         'effort_extract' => 'low', 'effort_suggest' => 'medium'],
                'timezone' => 'Europe/Paris',
                'debug' => false,
            ], true);
            $export = str_replace("'__DIR__/data/voyage.sqlite'", "__DIR__ . '/data/voyage.sqlite'", $export);
            $php = "<?php\n// Généré par install.php le " . date('Y-m-d H:i') . "\nreturn " . $export . ";\n";
            if (file_put_contents(vs_config_path(), $php, LOCK_EX) === false) {
                throw new RuntimeException('Impossible d\'écrire config.php : vérifiez les droits du dossier.');
            }
            @chmod(vs_config_path(), 0640);
            header('Location: index.php?installed=1');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e instanceof ValidationException ? implode(' ', $e->errors) : $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation — VoyageStudio</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E✈%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">
<main class="auth-card wide">
    <h1 class="brand">✈ VoyageStudio</h1>
    <?php if ($installed): ?>
        <p class="alert ok">L'application est déjà installée.</p>
        <p><a class="btn primary" href="index.php">Se connecter</a></p>
        <p class="muted small">Pour réinstaller, supprimez config.php (et la base) via votre FTP.</p>
    <?php else: ?>
        <h2>Installation</h2>
        <ul class="checks">
            <?php foreach ($checks as $label => $ok): ?>
                <li class="<?= $ok ? 'ok' : (str_contains($label, 'optionnel') ? 'warn' : 'ko') ?>"><?= $ok ? '✔' : '✖' ?> <?= htmlspecialchars($label) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php foreach ($errors as $e): ?><p class="alert error"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <fieldset class="span2"><legend>Votre agence</legend>
                <label>Nom de l'agence<input name="agence" value="<?= $v('agence') ?>" required></label>
                <label>Votre nom<input name="nom" value="<?= $v('nom') ?>"></label>
                <label>E-mail de connexion<input type="email" name="email" value="<?= $v('email') ?>" required autocomplete="username"></label>
                <label>Mot de passe (10 caractères min.)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
                <label>Confirmation<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
            </fieldset>
            <fieldset class="span2"><legend>Base de données</legend>
                <label class="inline"><input type="radio" name="driver" value="sqlite" <?= ($_POST['driver'] ?? 'sqlite') === 'sqlite' ? 'checked' : '' ?>> SQLite (recommandé, aucun réglage)</label>
                <label class="inline"><input type="radio" name="driver" value="mysql" <?= ($_POST['driver'] ?? '') === 'mysql' ? 'checked' : '' ?>> MySQL / MariaDB</label>
                <div class="form-grid">
                    <label>Hôte MySQL<input name="host" value="<?= $v('host', 'localhost') ?>"></label>
                    <label>Port<input name="port" value="<?= $v('port', '3306') ?>"></label>
                    <label>Base<input name="name" value="<?= $v('name') ?>"></label>
                    <label>Utilisateur<input name="user" value="<?= $v('user') ?>"></label>
                    <label>Mot de passe MySQL<input type="password" name="pass"></label>
                </div>
            </fieldset>
            <fieldset class="span2"><legend>Assistant IA (optionnel)</legend>
                <label>Clé API Anthropic<input name="ai_key" value="<?= $v('ai_key') ?>" placeholder="sk-ant-…" autocomplete="off"></label>
                <p class="muted small">Permet l'import de captures d'écran / PDF de réservation et les suggestions intelligentes. Modifiable plus tard dans config.php.</p>
            </fieldset>
            <label class="inline span2"><input type="checkbox" name="demo" value="1" <?= !empty($_POST['demo']) ? 'checked' : '' ?>> Charger des données de démonstration (clients, fournisseurs, un dossier exemple)</label>
            <div class="span2"><button class="btn primary" type="submit">Installer</button></div>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
