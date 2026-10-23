<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Connexion — <?= e(setting('shop_name')) ?></title><link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>"></head>
<body class="install">
<main class="install-card login-card">
  <h1><?= e(setting('shop_name')) ?></h1>
  <p class="muted">Espace d'administration</p>
  <?php if ($m = flash('error')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('admin/connexion')) ?>">
    <?= csrf_field() ?>
    <label>E-mail <input type="email" name="email" required autofocus autocomplete="username"></label>
    <label>Mot de passe <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
  </form>
</main>
</body>
</html>
