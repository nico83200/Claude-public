<?php
declare(strict_types=1);

/**
 * Page de connexion commune (centriva.fr) : e-mail + mot de passe.
 *  - super administrateur → administration de la plateforme (console.php) ;
 *  - utilisateur d'un client → session ouverte dans l'espace de son client (centriva.fr/<client>/) ;
 *  - même e-mail et même mot de passe chez plusieurs clients → choix de l'espace.
 * « Mot de passe oublié » envoie le lien depuis l'espace du client concerné.
 */
central_session();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$dir = instance_web_dir();
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$mode = isset($_GET['oubli']) ? 'forgot' : 'login';
$error = null;
$info = null;
$email = '';

if ($post && !hash_equals((string)$_SESSION['csrf'], (string)($_POST['_token'] ?? ''))) {
    $error = 'Session expirée : réessayez.';
    $post = false;
}

/** Ouvre la session dans l'espace du client par un jeton à usage unique. */
$enter = function (string $slug, int $uid) use ($dir): never {
    unset($_SESSION['central_choices']);
    setcookie('centriva_espace', $slug, ['expires' => time() + 400 * 86400, 'path' => $dir . '/', 'samesite' => 'Lax', 'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    header('Location: ' . central_space_url($slug) . 'index.php?r=login/sso&t=' . rawurlencode(central_handoff($slug, $uid)), true, 303);
    exit;
};

// Déjà connecté en super administrateur
if (!$post && central_superadmin() && !isset($_GET['changer'])) {
    header('Location: ' . $dir . '/console.php');
    exit;
}
// Appareil déjà utilisé dans un espace : on y retourne directement (sa session y est peut-être encore ouverte)
$remembered = (string)($_COOKIE['centriva_espace'] ?? '');
if (!$post && $mode === 'login' && $remembered !== '' && isset(instances_registry()[$remembered]) && !isset($_GET['changer'])) {
    header('Location: ' . central_space_url($remembered));
    exit;
}

if ($post && isset($_POST['choice'])) {
    // Choix de l'espace parmi plusieurs comptes (même e-mail, même mot de passe)
    $choices = $_SESSION['central_choices'] ?? null;
    $pick = (string)$_POST['choice'];
    if ($choices && time() - (int)$choices['at'] < 300 && isset($choices['list'][$pick])) {
        $enter($pick, (int)$choices['list'][$pick]['uid']);
    }
    $error = 'Choix expiré : reconnectez-vous.';
} elseif ($post && isset($_POST['code'])) {
    // Deuxième étape du super administrateur (application d'authentification)
    $p = $_SESSION['super_pending'] ?? null;
    $a = $p && time() - (int)$p['at'] < 300 ? superadmin_get((string)$p['id']) : null;
    if (!$a) {
        $error = 'Session expirée : reconnectez-vous.';
    } elseif (central_throttled()) {
        $error = 'Trop d\'essais : réessayez dans un quart d\'heure.';
    } elseif (totp_match((string)$a['totp'], (string)$_POST['code']) === null) {
        central_throttled(true);
        $error = 'Code incorrect.';
        $mode = 'code';
    } else {
        unset($_SESSION['super_pending']);
        central_superadmin_login($a);
        header('Location: ' . $dir . '/console.php', true, 303);
        exit;
    }
} elseif ($post && $mode === 'forgot') {
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    if (central_throttled()) {
        $error = 'Trop d\'essais : réessayez dans un quart d\'heure.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        central_throttled(true); // limite aussi le nombre d'envois
        require_once APP . '/instances_admin.php';
        foreach (central_find_email($email) as $slug) {
            try {
                instance_run($slug, function () use ($email, $slug, $dir) {
                    if (!setting('app_url') && !empty($_SERVER['HTTP_HOST'])) {
                        set_setting('app_url', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . central_space_url($slug));
                    }
                    $u = one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
                    if ($u && mail_case_enabled('password_reset')) {
                        send_mail($u['email'], 'Réinitialisation de votre mot de passe', mail_template($u, 'Réinitialisation de votre mot de passe',
                            'Vous avez demandé à changer votre mot de passe ' . app_name() . '. Le lien ci-dessous est valable une heure. Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.',
                            url('reset', ['token' => password_reset_create($u)])));
                        mail_queue_process(5);
                    }
                });
            } catch (Throwable $e) {
                error_log('[connexion] ' . $e->getMessage());
            }
        }
        $info = 'Si un compte existe avec cette adresse, un e-mail vient de vous être envoyé avec un lien pour choisir un nouveau mot de passe. Sans e-mail d\'ici quelques minutes, demandez à l\'administrateur de votre établissement.';
    } else {
        $error = 'Adresse e-mail invalide.';
    }
} elseif ($post) {
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (central_throttled()) {
        $error = 'Trop de tentatives infructueuses : réessayez dans un quart d\'heure, ou utilisez « Mot de passe oublié ».';
    } elseif (($a = superadmin_find($email)) && password_verify($password, (string)$a['hash'])) {
        if (!empty($a['totp'])) {
            session_regenerate_id(true);
            $_SESSION['super_pending'] = ['id' => $a['id'], 'at' => time()];
            $mode = 'code';
        } else {
            central_superadmin_login($a);
            header('Location: ' . $dir . '/console.php', true, 303);
            exit;
        }
    } else {
        $found = central_find_accounts($email, $password);
        if (count($found) === 1) {
            $enter($found[0]['slug'], $found[0]['uid']);
        } elseif ($found) {
            $_SESSION['central_choices'] = ['at' => time(), 'list' => array_column($found, null, 'slug')];
            $mode = 'choose';
        } else {
            central_throttled(true);
            $error = 'Identifiants incorrects.';
        }
    }
}

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$tok = '<input type="hidden" name="_token" value="' . $h($_SESSION['csrf']) . '">';
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion · Centriva</title><meta name="robots" content="noindex">
<link rel="icon" type="image/svg+xml" href="<?= $dir ?>/assets/brand/centriva-mark.svg">
<style>
:root{--bg:#f4f6fb;--card:#fff;--ink:#0d1240;--muted:#64748b;--line:#e2e8f0}
@media(prefers-color-scheme:dark){:root{--bg:#0e1122;--card:#171b33;--ink:#e2e8f0;--muted:#94a3b8;--line:#2a3055}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:1.5rem 1rem;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,-apple-system,sans-serif}
.box{width:100%;max-width:420px;background:var(--card);border:1px solid var(--line);border-radius:18px;padding:1.8rem 1.6rem;box-shadow:0 10px 40px rgba(15,23,42,.08)}
h1{font-size:1.35rem;margin:1.2rem 0 .3rem}p{color:var(--muted);line-height:1.5;margin:.3rem 0 1rem}label{font-weight:600;font-size:.9rem;display:block}
input{width:100%;margin:.4rem 0 1rem;padding:.75rem .8rem;border:1px solid var(--line);border-radius:10px;font:inherit;background:var(--card);color:var(--ink)}
.btn{display:block;width:100%;padding:.8rem;border:0;border-radius:12px;background:linear-gradient(135deg,#0a9cf7,#2a1fc4 55%,#ff3d9a);color:#fff;font:inherit;font-weight:700;cursor:pointer;text-align:center;text-decoration:none}
.choice{display:block;width:100%;text-align:left;padding:.85rem 1rem;margin:.5rem 0;border:1px solid var(--line);border-radius:12px;background:var(--card);color:var(--ink);font:inherit;font-weight:600;cursor:pointer}
.choice:hover{border-color:#6366f1}.err{background:#fef2f2;color:#991b1b;padding:.7rem .9rem;border-radius:10px;font-size:.92rem}
.ok{background:#ecfdf5;color:#065f46;padding:.7rem .9rem;border-radius:10px;font-size:.92rem}.links{text-align:center;margin-top:1rem;font-size:.9rem}.links a{color:#4f46e5}
</style></head><body><div class="box">
<img src="<?= $dir ?>/assets/brand/centriva-logo.svg" alt="Centriva" height="40">
<?php if ($mode === 'choose'): ?>
  <h1>Choisissez votre espace</h1>
  <p>Votre compte existe dans plusieurs établissements :</p>
  <form method="post"><?= $tok ?><?php foreach ($_SESSION['central_choices']['list'] as $slug => $c): ?><button class="choice" name="choice" value="<?= $h($slug) ?>"><?= $h($c['name']) ?> →</button><?php endforeach; ?></form>
<?php elseif ($mode === 'code'): ?>
  <h1>Double authentification</h1>
  <p>Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
  <?php if ($error): ?><p class="err"><?= $h($error) ?></p><?php endif; ?>
  <form method="post"><?= $tok ?><label for="code">Code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus><button class="btn">Valider</button></form>
<?php elseif ($mode === 'forgot'): ?>
  <h1>Mot de passe oublié</h1>
  <?php if ($info): ?><p class="ok"><?= $h($info) ?></p><?php else: ?>
  <p>Indiquez l'adresse e-mail de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
  <?php if ($error): ?><p class="err"><?= $h($error) ?></p><?php endif; ?>
  <form method="post" action="<?= $dir ?>/?oubli=1"><?= $tok ?><label for="email">Adresse e-mail</label><input id="email" type="email" name="email" required autofocus value="<?= $h($email) ?>"><button class="btn">Envoyer le lien</button></form>
  <?php endif; ?>
  <div class="links"><a href="<?= $dir ?>/?changer=1">← Retour à la connexion</a></div>
<?php else: ?>
  <h1>Connexion</h1>
  <p>Bienvenue ! Connectez-vous avec votre adresse e-mail professionnelle.</p>
  <?php if ($error): ?><p class="err"><?= $h($error) ?></p><?php endif; ?>
  <form method="post" action="<?= $dir ?>/"><?= $tok ?>
    <label for="email">Adresse e-mail</label><input id="email" type="email" name="email" required autofocus autocomplete="username" value="<?= $h($email) ?>">
    <label for="password">Mot de passe</label><input id="password" type="password" name="password" required autocomplete="current-password">
    <button class="btn">Se connecter</button></form>
  <div class="links"><a href="<?= $dir ?>/?oubli=1">Mot de passe oublié ?</a></div>
<?php endif; ?>
</div></body></html>
