<h1>Demande d'accès</h1>
<p class="muted">Votre compte sera activé par le service achats, qui validera le ou les centres auxquels vous avez accès.</p>
<?php foreach ($errors as $err): ?><div class="flash flash-error"><?= icon('alert') ?><div><?= e($err) ?></div></div><?php endforeach; ?>
<form method="post" class="card card-body mt-2">
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label>Prénom</label><input type="text" name="first_name" value="<?= e($old['first_name'] ?? '') ?>" required></div>
    <div class="field"><label>Nom</label><input type="text" name="last_name" value="<?= e($old['last_name'] ?? '') ?>" required></div>
    <div class="field full"><label>E-mail professionnel</label><input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required></div>
    <div class="field"><label>Fonction</label>
      <select name="job"><?php foreach (job_choices() as $j): ?><option <?= ($old['job'] ?? '') === $j ? 'selected' : '' ?>><?= e($j) ?></option><?php endforeach; ?></select>
    </div>
    <div class="field"><label>Téléphone</label><input type="tel" name="phone" value="<?= e($old['phone'] ?? '') ?>"></div>
    <div class="field full"><label>Mot de passe (8 caractères min.)</label><input type="password" name="password" minlength="8" required autocomplete="new-password"></div>
  </div>
  <div class="field">
    <label>Centre(s) où vous travaillez</label>
    <?php foreach ($centers as $c): ?>
      <label class="check"><input type="checkbox" name="centers[]" value="<?= (int)$c['id'] ?>"> <?= e($c['name']) ?> <small><?= e($c['city']) ?></small></label>
    <?php endforeach; ?>
    <?php if (!$centers): ?><p class="muted">Aucun centre n'est encore configuré.</p><?php endif; ?>
  </div>
  <button class="btn btn-primary btn-lg" style="width:100%" type="submit">Envoyer ma demande</button>
</form>
<p class="text-center mt-2"><a href="<?= url('login') ?>"><?= icon('arrow-left', 16) ?> Retour à la connexion</a></p>
