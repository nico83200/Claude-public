<?php $invite = input('invite') === '1'; ?>
<h1><?= $invite ? 'Bienvenue sur ' . e(app_name()) . ' !' : 'Nouveau mot de passe' ?></h1>
<?php if ($invite && $reset): ?><p>Bonjour <?= e($reset['first_name']) ?>, votre compte est prêt : choisissez votre mot de passe pour vous connecter.</p><?php endif; ?>
<?php if (!$reset): ?>
  <div class="flash flash-error"><?= icon('alert') ?><div>Ce lien n'est plus valable (expiré ou déjà utilisé). <?= $invite ? 'Demandez une nouvelle invitation à votre service achats, ou utilisez « Mot de passe oublié ».' : 'Faites une nouvelle demande.' ?></div></div>
  <a class="btn btn-primary" href="<?= url('forgot') ?>">Nouvelle demande</a>
<?php else: ?>
  <p class="muted">Compte : <?= e($reset['email']) ?></p>
  <?php if ($error): ?><div class="flash flash-error"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
  <form method="post" class="card card-body mt-2">
    <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><?php if ($invite): ?><input type="hidden" name="invite" value="1"><?php endif; ?>
    <div class="field"><label>Nouveau mot de passe (8 caractères min.)</label><input type="password" name="password" minlength="8" required autofocus autocomplete="new-password"></div>
    <div class="field"><label>Confirmation</label><input type="password" name="confirm" minlength="8" required autocomplete="new-password"></div>
    <button class="btn btn-primary btn-lg" style="width:100%" type="submit">Enregistrer</button>
  </form>
<?php endif; ?>
