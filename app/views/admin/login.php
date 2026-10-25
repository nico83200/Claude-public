<?php $mode ??= 'login'; ?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $mode === 'login' ? 'Connexion' : 'Mot de passe' ?> — <?= e(setting('shop_name')) ?></title><link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>"></head>
<body class="install">
<main class="install-card login-card">
  <h1><?= e(setting('shop_name')) ?></h1>
  <p class="muted">Espace d'administration</p>
  <?php if ($m = flash('error')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>
  <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>

  <?php if ($mode === 'forgot'): ?>
    <h2>Mot de passe oublié</h2>
    <p class="small muted">Indiquez l'adresse e-mail de votre compte administrateur : vous recevrez un lien (valable 1 heure) pour choisir un nouveau mot de passe.</p>
    <form method="post" action="<?= e(url('admin/mot-de-passe-oublie')) ?>">
      <?= csrf_field() ?>
      <label>E-mail <input type="email" name="email" required autofocus autocomplete="username"></label>
      <button class="btn btn-primary btn-block" type="submit">Recevoir le lien</button>
    </form>
    <p class="small center mt"><a href="<?= e(url('admin/connexion')) ?>">← Retour à la connexion</a></p>

  <?php elseif ($mode === 'reset'): ?>
    <h2>Nouveau mot de passe</h2>
    <form method="post" action="<?= e(url('admin/nouveau-mot-de-passe/' . $token)) ?>">
      <?= csrf_field() ?>
      <label>Nouveau mot de passe (10 caractères minimum) <input type="password" name="password" required minlength="10" autofocus autocomplete="new-password"></label>
      <label>Confirmez <input type="password" name="password_confirm" required minlength="10" autocomplete="new-password"></label>
      <button class="btn btn-primary btn-block" type="submit">Enregistrer et me connecter</button>
    </form>

  <?php else: ?>
    <form method="post" action="<?= e(url('admin/connexion')) ?>">
      <?= csrf_field() ?>
      <label>E-mail <input type="email" name="email" required autofocus autocomplete="username"></label>
      <label>Mot de passe <input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
    </form>
    <p class="small center mt"><a href="<?= e(url('admin/mot-de-passe-oublie')) ?>">Mot de passe oublié ?</a></p>
  <?php endif; ?>
</main>
<script src="<?= e(asset('assets/js/password-toggle.js')) ?>" defer></script>
</body>
</html>
