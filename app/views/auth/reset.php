<h1>Nouveau mot de passe</h1>
<?php if (!$reset): ?>
  <div class="flash flash-error"><?= icon('alert') ?><div>Ce lien n'est plus valable (expiré ou déjà utilisé). Faites une nouvelle demande.</div></div>
  <a class="btn btn-primary" href="<?= url('forgot') ?>">Nouvelle demande</a>
<?php else: ?>
  <p class="muted">Compte : <?= e($reset['email']) ?></p>
  <?php if ($error): ?><div class="flash flash-error"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
  <form method="post" class="card card-body mt-2">
    <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="field"><label>Nouveau mot de passe (8 caractères min.)</label><input type="password" name="password" minlength="8" required autofocus autocomplete="new-password"></div>
    <div class="field"><label>Confirmation</label><input type="password" name="confirm" minlength="8" required autocomplete="new-password"></div>
    <button class="btn btn-primary btn-lg" style="width:100%" type="submit">Enregistrer</button>
  </form>
<?php endif; ?>
