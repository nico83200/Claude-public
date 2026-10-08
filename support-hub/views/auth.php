<?php
/**
 * Connexion à la console : identifiant + mot de passe, puis code de double authentification s'il est activé pour le compte.
 * Blocage 15 minutes après 5 échecs depuis la même adresse (avec alerte). À la toute première visite : création du premier compte.
 */
defined('HUB') || exit;

$error = null;
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '?');
$ipKey = substr(sha1($ip), 0, 12);
$hasUsers = (bool)hone('SELECT id FROM users LIMIT 1');

function login_fail(string $ipKey, string $ip): string
{
    $fails = (int)hsetting('login_fails_' . $ipKey, '0') + 1;
    hset('login_fails_' . $ipKey, (string)$fails);
    if ($fails >= 5) {
        hset('login_lock_' . $ipKey, (string)(time() + 900));
        hset('login_fails_' . $ipKey, '0');
        hub_notify('Console verrouillée', 'Cinq échecs de connexion à la console : accès bloqué 15 minutes pour l\'adresse ' . $ip . '.', hub_base_url());
        return 'Trop d\'essais : la connexion est bloquée 15 minutes.';
    }
    return 'Identifiant ou mot de passe incorrect (' . (5 - $fails) . ' essai(s) restant(s)).';
}

function login_ok(array $u, string $ipKey): never
{
    hset('login_fails_' . $ipKey, '0');
    unset($_SESSION['hub_pending']);
    session_regenerate_id(true);
    $_SESSION['hub_uid'] = (int)$u['id'];
    hq('UPDATE users SET last_login = ? WHERE id = ?', [hnow(), $u['id']]);
    if (hsetting('legacy_login') === '1' && strtolower($u['username']) === 'admin') {
        hset('legacy_login', null);
        flash('Bienvenue dans la nouvelle console. Personnalisez votre identifiant et ajoutez vos collaborateurs dans « Mon compte » et « Comptes ».');
    }
    go('index.php' . (isset($_GET['c']) ? '?c=' . (int)$_GET['c'] : ''));
}

$hubUser = hub_user((int)($_SESSION['hub_uid'] ?? 0));
if (!$hubUser) {
    unset($_SESSION['hub_uid']);
    $locked = (int)hsetting('login_lock_' . $ipKey, '0') > time();
    $pending = ($_SESSION['hub_pending']['at'] ?? 0) > time() - 300 ? hub_user((int)$_SESSION['hub_pending']['id']) : null;
    if ($post && !$locked) {
        $step = (string)($_POST['step'] ?? 'password');
        if (!$hasUsers) {
            // Toute première connexion : création du compte administrateur
            $username = trim((string)($_POST['username'] ?? ''));
            $pw = (string)($_POST['password'] ?? '');
            if (!hub_valid_username($username)) {
                $error = 'Identifiant : 3 caractères minimum, lettres, chiffres, point, tiret ou @.';
            } elseif (mb_strlen($pw) < 10 || $pw !== (string)($_POST['password2'] ?? '')) {
                $error = 'Choisissez un mot de passe d\'au moins 10 caractères, saisi deux fois à l\'identique.';
            } else {
                hq("INSERT INTO users (username, name, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', ?)",
                    [$username, mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80) ?: $username, password_hash($pw, PASSWORD_DEFAULT), hnow()]);
                login_ok(hone('SELECT * FROM users WHERE username = ?', [$username]), $ipKey);
            }
        } elseif ($step === 'totp' && $pending) {
            if ($pending['totp_secret'] && totp_verify($pending['totp_secret'], (string)($_POST['code'] ?? ''))) {
                login_ok($pending, $ipKey);
            }
            $error = login_fail($ipKey, $ip);
        } else {
            $u = hone('SELECT * FROM users WHERE username = ? AND active = 1', [trim((string)($_POST['username'] ?? ''))]);
            // Vérification même si le compte n'existe pas : même durée de réponse
            $okPw = password_verify((string)($_POST['password'] ?? ''), $u['password_hash'] ?? '$2y$10$NJDuvSA7ysUGEl.JPSNs0.X/yf268YJ2Go4G.7qV/2hnRLCygBswK');
            if ($u && $okPw) {
                if ($u['totp_secret']) {
                    $_SESSION['hub_pending'] = ['id' => (int)$u['id'], 'at' => time()];
                    $pending = $u;
                } else {
                    login_ok($u, $ipKey);
                }
            } else {
                $error = login_fail($ipKey, $ip);
            }
        }
    } elseif ($locked) {
        $error = 'Trop d\'essais : la connexion est bloquée quelques minutes.';
    }
    $askCode = $pending && $pending['totp_secret'];
    page_head('Connexion');
    ?>
    <main class="login"><form method="post" class="card" autocomplete="on">
      <img src="assets/nlapps-mark.svg" alt="" width="52" height="52" style="margin:auto">
      <h1>Assistance NLapps</h1>
      <p class="muted"><?= $askCode ? 'Saisissez le code à 6 chiffres affiché par votre application d\'authentification.' : ($hasUsers ? 'Connectez-vous avec votre identifiant et votre mot de passe.' : 'Première connexion : créez le compte administrateur de la console.') ?></p>
      <?php if (hsetting('legacy_login') === '1' && !$askCode): ?>
        <div class="note">Nouveau : la console fonctionne désormais avec des comptes. Votre accès actuel est devenu le compte <b>admin</b> : identifiant <b>admin</b>, même mot de passe.</div>
      <?php endif; ?>
      <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
      <?= csrf_input() ?>
      <?php if ($askCode): ?>
        <input type="hidden" name="step" value="totp">
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123 456" required autofocus style="text-align:center;font-size:1.4rem;letter-spacing:.2em">
        <button class="btn primary" type="submit" style="justify-content:center">Valider</button>
      <?php else: ?>
        <?php if (!$hasUsers): ?><input type="text" name="name" placeholder="Votre nom (ex : Nicolas Lefèvre)" autocomplete="name"><?php endif; ?>
        <input type="text" name="username" placeholder="Identifiant" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= h((string)($_POST['username'] ?? '')) ?>">
        <input type="password" name="password" placeholder="Mot de passe" required autocomplete="<?= $hasUsers ? 'current-password' : 'new-password' ?>">
        <?php if (!$hasUsers): ?><input type="password" name="password2" placeholder="Confirmez le mot de passe" required autocomplete="new-password"><?php endif; ?>
        <button class="btn primary" type="submit" style="justify-content:center"><?= $hasUsers ? 'Se connecter' : 'Créer le compte' ?></button>
      <?php endif; ?>
    </form></main>
    <?php
    page_foot();
    exit;
}
