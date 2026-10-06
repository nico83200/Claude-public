<h1>Connexion</h1>
<p class="muted">Bienvenue ! Connectez-vous pour passer vos commandes.</p>
<?php if ($error): ?><div class="flash flash-error"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
<form method="post" class="card card-body mt-2">
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Adresse e-mail</label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
  </div>
  <div class="field">
    <label for="password">Mot de passe</label>
    <input id="password" type="password" name="password" required autocomplete="current-password">
  </div>
  <button class="btn btn-primary btn-lg" style="width:100%" type="submit">Se connecter</button>
</form>
<?php if (setting('allow_registration', '1') === '1'): ?>
<p class="text-center mt-2">Pas encore de compte ? <a href="<?= url('register') ?>">Demander un accès</a></p>
<?php endif; ?>
<p class="text-center" style="font-size:.9rem"><a href="<?= url('forgot') ?>">Mot de passe oublié ?</a></p>
