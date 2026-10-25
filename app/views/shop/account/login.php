<?php $retour = $return !== '' ? '<input type="hidden" name="retour" value="' . e($return) . '">' : ''; ?>
<section class="section">
  <div class="container">
    <p class="script-accent center">bienvenue au club</p>
    <h1 class="center">Mon <em>compte</em></h1>
    <div class="account-shell">
      <form action="<?= e(url('compte/connexion')) ?>" method="post" class="form-card" id="connexion">
        <?= csrf_field() ?><?= $retour ?>
        <h2>Déjà cliente ?</h2>
        <p class="muted">Connecte-toi pour suivre tes commandes.</p>
        <div class="field"><label for="login-email">Adresse e-mail</label><input id="login-email" type="email" name="email" required autocomplete="email" value="<?= $tab === 'login' ? e(old('email')) : '' ?>" <?= $tab === 'login' ? 'autofocus' : '' ?>></div>
        <div class="field"><label for="login-password">Mot de passe</label><input id="login-password" type="password" name="password" required autocomplete="current-password"></div>
        <button class="btn btn-primary btn-block" type="submit">Me connecter</button>
        <div class="form-links"><a href="<?= e(url('compte/mot-de-passe-oublie')) ?>">Mot de passe oublié ?</a><a href="<?= e(url('suivi')) ?>">Suivre une commande sans compte</a></div>
      </form>

      <form action="<?= e(url('compte/inscription')) ?>" method="post" class="form-card" id="inscription">
        <?= csrf_field() ?><?= $retour ?>
        <h2>Nouvelle au club ?</h2>
        <ul class="account-perks">
          <li><?= icon('truck', 18) ?> Le suivi de toutes tes commandes au même endroit</li>
          <li><?= icon('heart', 18) ?> Tes portraits et créations retrouvés en un clic</li>
          <li><?= icon('gift', 18) ?> Des surprises réservées aux membres</li>
        </ul>
        <div class="field"><label for="reg-name">Prénom</label><input id="reg-name" type="text" name="name" autocomplete="given-name" maxlength="120" value="<?= e(old('name')) ?>"></div>
        <div class="field"><label for="reg-email">Adresse e-mail</label><input id="reg-email" type="email" name="email" required autocomplete="email" value="<?= $tab === 'register' ? e(old('email')) : '' ?>" <?= $tab === 'register' ? 'autofocus' : '' ?>></div>
        <div class="field"><label for="reg-password">Mot de passe</label><input id="reg-password" type="password" name="password" required minlength="<?= App\Services\Account::MIN_PASSWORD ?>" autocomplete="new-password"><small class="muted"><?= App\Services\Account::MIN_PASSWORD ?> caractères minimum.</small></div>
        <div class="field"><label for="reg-password2">Confirme le mot de passe</label><input id="reg-password2" type="password" name="password_confirm" required minlength="<?= App\Services\Account::MIN_PASSWORD ?>" autocomplete="new-password"></div>
        <button class="btn btn-ghost btn-block" type="submit">Créer mon compte</button>
        <p class="muted small">En créant un compte, tu acceptes notre <a href="<?= e(url('page/confidentialite')) ?>">politique de confidentialité</a>. Tu peux le supprimer à tout moment.</p>
      </form>
    </div>
  </div>
</section>
