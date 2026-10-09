<?php if (demo_mode()): ?>
<h1>Découvrez Centriva</h1>
<p class="muted">Démo en accès libre : choisissez un profil, vous êtes connecté en un clic. Les données sont fictives et remises à zéro chaque nuit.</p>
<div class="demo-profiles">
  <?php foreach (DEMO_PROFILES as $role => [$demoEmail, $label, $desc, $ic]): ?>
    <form method="post" action="<?= url('demo/login') ?>"><?= csrf_field() ?><input type="hidden" name="as" value="<?= e($role) ?>">
      <button type="submit" class="demo-profile"><span class="demo-ic"><?= icon($ic, 22) ?></span><span><strong><?= e($label) ?></strong><small><?= e($desc) ?></small></span><?= icon('chevron-right', 18) ?></button></form>
  <?php endforeach; ?>
</div>
<details class="mt-2"><summary class="muted" style="cursor:pointer">Se connecter avec un e-mail et un mot de passe</summary>
<?php endif; ?>
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
<?php if (demo_mode()): ?></details><?php endif; ?>
<?php if (current_instance() && !demo_mode()): ?><p class="text-center" style="font-size:.85rem"><a href="<?= e(instance_web_dir()) ?>/?changer=1">Se connecter avec un autre compte</a></p><?php endif; ?>
