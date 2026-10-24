<section class="section">
  <div class="container narrow">
    <h1>Nouveau <em>mot de passe</em></h1>
    <form action="<?= e(url('compte/nouveau-mot-de-passe/' . $token)) ?>" method="post" class="form-card">
      <?= csrf_field() ?>
      <div class="field"><label for="password">Nouveau mot de passe</label><input id="password" type="password" name="password" required minlength="<?= App\Services\Account::MIN_PASSWORD ?>" autocomplete="new-password" autofocus><small class="muted"><?= App\Services\Account::MIN_PASSWORD ?> caractères minimum.</small></div>
      <div class="field"><label for="password2">Confirmez</label><input id="password2" type="password" name="password_confirm" required minlength="<?= App\Services\Account::MIN_PASSWORD ?>" autocomplete="new-password"></div>
      <button class="btn btn-primary" type="submit">Enregistrer et me connecter</button>
    </form>
  </div>
</section>
