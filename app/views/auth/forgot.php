<h1>Mot de passe oublié</h1>
<?php if ($sent): ?>
  <div class="flash flash-success"><?= icon('mail') ?><div>Si un compte actif correspond à cette adresse, un e-mail contenant un lien de réinitialisation (valable une heure) vient d'être envoyé. Pensez à vérifier les courriers indésirables.</div></div>
  <a class="btn" href="<?= url('login') ?>"><?= icon('arrow-left', 16) ?> Retour à la connexion</a>
<?php else: ?>
  <p class="muted">Indiquez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
  <?php if (!$mailOn): ?><div class="flash flash-info"><?= icon('info') ?><div>La réinitialisation par e-mail n'est pas activée : contactez le service achats, qui peut réinitialiser votre mot de passe.</div></div>
  <?php else: ?>
  <form method="post" class="card card-body mt-2">
    <?= csrf_field() ?>
    <div class="field"><label for="email">Adresse e-mail</label><input id="email" type="email" name="email" required autofocus autocomplete="username"></div>
    <button class="btn btn-primary btn-lg" style="width:100%" type="submit">Recevoir le lien</button>
  </form>
  <?php endif; ?>
  <p class="text-center mt-2"><a href="<?= url('login') ?>"><?= icon('arrow-left', 16) ?> Retour à la connexion</a></p>
<?php endif; ?>
