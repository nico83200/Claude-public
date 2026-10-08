<?php
/** Mon compte : identifiant, nom, e-mail, mot de passe et double authentification de la personne connectée. */
defined('HUB') || exit;

$totpNew = $_SESSION['totp_new'] ?? null;
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1>Mon compte</h1>
  <form method="post" class="card">
    <?= csrf_input() ?><input type="hidden" name="action" value="account_save">
    <h2>Identité et identifiant de connexion</h2>
    <div class="grid2">
      <div><label>Nom affiché <small class="muted">(signe vos réponses dans la console)</small></label><input type="text" name="name" value="<?= h($hubUser['name']) ?>" required maxlength="80"></div>
      <div><label>Identifiant</label><input type="text" name="username" value="<?= h($hubUser['username']) ?>" required autocomplete="username" autocapitalize="none" spellcheck="false"></div>
      <div><label>E-mail <small class="muted">(facultatif)</small></label><input type="email" name="email" value="<?= h((string)$hubUser['email']) ?>"></div>
      <div><label>Rôle</label><input type="text" value="<?= $hubUser['role'] === 'admin' ? 'Administrateur : toute la console' : 'Conseiller : conversations, FAQ et vidéos' ?>" disabled></div>
    </div>
    <button class="btn primary" style="margin-top:.8rem">Enregistrer</button>
  </form>

  <form method="post" class="card">
    <?= csrf_input() ?><input type="hidden" name="action" value="password_change">
    <h2>Mot de passe</h2>
    <div class="grid2">
      <div><label>Mot de passe actuel</label><input type="password" name="current" required autocomplete="current-password"></div>
      <div><label>Nouveau (10 caractères min.)</label><input type="password" name="new" required autocomplete="new-password" minlength="10"></div>
      <div><label>Confirmation</label><input type="password" name="new2" required autocomplete="new-password" minlength="10"></div>
    </div>
    <button class="btn primary" style="margin-top:.8rem">Changer le mot de passe</button>
  </form>

  <div class="card" id="security">
    <h2>Double authentification <?= $hubUser['totp_secret'] ? '<span class="tag green">activée</span>' : '<span class="tag amber">désactivée</span>' ?></h2>
    <?php if ($hubUser['totp_secret']): ?>
      <p class="muted" style="margin-top:0">Un code à 6 chiffres vous est demandé à chaque connexion. Pour la désactiver :</p>
      <form method="post" class="row"><?= csrf_input() ?><input type="hidden" name="action" value="totp_disable">
        <input type="password" name="current" placeholder="Mot de passe" required autocomplete="current-password"><input name="code" placeholder="Code à 6 chiffres" inputmode="numeric" required><button class="btn danger">Désactiver</button></form>
    <?php elseif ($totpNew): $uri = 'otpauth://totp/' . rawurlencode('Assistance NLapps:' . $hubUser['username']) . '?secret=' . $totpNew . '&issuer=NLapps&digits=6&period=30'; ?>
      <p>1. Dans Google Authenticator, Microsoft Authenticator ou Authy, ajoutez un compte en scannant ce code (ou saisissez la clé <code><?= h(trim(chunk_split($totpNew, 4, ' '))) ?></code>) :</p>
      <div data-qr="<?= h($uri) ?>" style="background:#fff;padding:8px;display:inline-block;border:1px solid var(--line);border-radius:10px"></div>
      <form method="post" class="row" style="margin-top:.6rem"><?= csrf_input() ?><input type="hidden" name="action" value="totp_enable">
        <span>2. Code affiché :</span><input name="code" placeholder="123456" inputmode="numeric" autocomplete="one-time-code" required style="max-width:160px"><button class="btn primary">Activer</button></form>
    <?php else: ?>
      <p class="muted" style="margin-top:0">Fortement conseillé : un code à 6 chiffres, généré par une application sur votre téléphone, sera demandé à chaque connexion en plus du mot de passe.</p>
      <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="totp_start"><button class="btn primary">Configurer</button></form>
    <?php endif; ?>
    <p class="muted"><small>Après 5 échecs de connexion depuis une même adresse, la connexion est bloquée 15 minutes et vous êtes alerté.</small></p>
  </div>
</main>
