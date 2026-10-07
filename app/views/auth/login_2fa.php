<h1>Vérification</h1>
<p class="muted">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
<?php if ($error): ?><div class="flash flash-error"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
<form method="post" class="card card-body mt-2">
  <?= csrf_field() ?>
  <div class="field">
    <label for="code">Code de vérification</label>
    <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus style="font-size:1.5rem;letter-spacing:.25em;text-align:center">
  </div>
  <button class="btn btn-primary btn-lg" style="width:100%" type="submit">Valider</button>
</form>
<p class="text-center" style="font-size:.9rem">Téléphone perdu ? Un autre administrateur peut réinitialiser votre double authentification. <a href="<?= url('login') ?>">Retour</a></p>
