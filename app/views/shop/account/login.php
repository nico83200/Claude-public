<?php $retour = $return !== '' ? '<input type="hidden" name="retour" value="' . e($return) . '">' : ''; ?>
<section class="section">
  <div class="container">
    <p class="script-accent center">bienvenue au club</p>
    <h1 class="center">Mon <em>compte</em></h1>
    <?php if (App\Services\GoogleAuth::enabled()): ?>
      <div class="google-auth">
        <a class="btn btn-google" href="<?= e(url('compte/google', $return !== '' ? ['retour' => $return] : [])) ?>">
          <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2c-2 1.5-4.5 2.4-7.2 2.4-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
          Continuer avec Google
        </a>
        <p class="muted small">Connexion ou création de compte en un clic, sans mot de passe à retenir.</p>
        <p class="google-or"><span>ou avec ton e-mail</span></p>
      </div>
    <?php endif; ?>
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
