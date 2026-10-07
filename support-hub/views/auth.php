<?php
/** Connexion à la console : mot de passe (blocage après 5 échecs), puis code de double authentification s'il est activé. */
defined('HUB') || exit;

$hash = hsetting('password_hash');
$totp = hsetting('totp_secret');
$error = null;

function login_fail(): string
{
    $fails = (int)hsetting('login_fails', '0') + 1;
    hset('login_fails', (string)$fails);
    if ($fails >= 5) {
        hset('login_lock', (string)(time() + 900));
        hset('login_fails', '0');
        hub_notify('Console verrouillée', 'Cinq échecs de connexion à la console : accès bloqué 15 minutes. IP : ' . ($_SERVER['REMOTE_ADDR'] ?? '?'), hub_base_url());
        return 'Trop d\'essais : la console est verrouillée 15 minutes.';
    }
    return 'Identifiants incorrects (' . (5 - $fails) . ' essai(s) restant(s)).';
}

if (!($_SESSION['hub_ok'] ?? false)) {
    $locked = (int)hsetting('login_lock', '0') > time();
    if ($post && !$locked) {
        $step = (string)($_POST['step'] ?? 'password');
        if (!$hash) {
            $pw = (string)($_POST['password'] ?? '');
            if (mb_strlen($pw) < 10 || $pw !== (string)($_POST['password2'] ?? '')) {
                $error = 'Choisissez un mot de passe d\'au moins 10 caractères, saisi deux fois à l\'identique.';
            } else {
                hset('password_hash', password_hash($pw, PASSWORD_DEFAULT));
                session_regenerate_id(true);
                $_SESSION['hub_ok'] = true;
            }
        } elseif ($step === 'totp' && ($_SESSION['hub_pw_ok'] ?? 0) > time() - 300) {
            if ($totp && totp_verify($totp, (string)($_POST['code'] ?? ''))) {
                unset($_SESSION['hub_pw_ok']);
                hset('login_fails', '0');
                session_regenerate_id(true);
                $_SESSION['hub_ok'] = true;
            } else {
                $error = login_fail();
            }
        } elseif (password_verify((string)($_POST['password'] ?? ''), $hash)) {
            if ($totp) {
                $_SESSION['hub_pw_ok'] = time();
            } else {
                hset('login_fails', '0');
                session_regenerate_id(true);
                $_SESSION['hub_ok'] = true;
            }
        } else {
            $error = login_fail();
        }
        if ($_SESSION['hub_ok'] ?? false) {
            go('index.php' . (isset($_GET['c']) ? '?c=' . (int)$_GET['c'] : ''));
        }
    } elseif ($locked) {
        $error = 'Trop d\'essais : la console est verrouillée quelques minutes.';
    }
    $askCode = $totp && ($_SESSION['hub_pw_ok'] ?? 0) > time() - 300;
    page_head('Connexion');
    ?>
    <main class="login"><form method="post" class="card">
      <img src="assets/nlapps-mark.svg" alt="" width="52" height="52" style="margin:auto">
      <h1>Assistance NLapps</h1>
      <p class="muted"><?= $askCode ? 'Saisissez le code à 6 chiffres affiché par votre application d\'authentification.' : ($hash ? 'Connectez-vous pour répondre aux utilisateurs.' : 'Première connexion : choisissez le mot de passe de la console.') ?></p>
      <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
      <?= csrf_input() ?>
      <?php if ($askCode): ?>
        <input type="hidden" name="step" value="totp">
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123 456" required autofocus style="text-align:center;font-size:1.4rem;letter-spacing:.2em">
        <button class="btn primary" type="submit" style="justify-content:center">Valider</button>
      <?php else: ?>
        <input type="password" name="password" placeholder="Mot de passe" required autofocus autocomplete="<?= $hash ? 'current-password' : 'new-password' ?>">
        <?php if (!$hash): ?><input type="password" name="password2" placeholder="Confirmez le mot de passe" required autocomplete="new-password"><?php endif; ?>
        <button class="btn primary" type="submit" style="justify-content:center"><?= $hash ? 'Se connecter' : 'Créer l\'accès' ?></button>
      <?php endif; ?>
    </form></main>
    <?php
    page_foot();
    exit;
}
