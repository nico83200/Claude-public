<?php
use App\Services\Account;
use App\Services\OrderService;
$first = Account::firstName($customer);
?>
<section class="section">
  <div class="container">
    <div class="account-head">
      <span class="account-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($first, 0, 1))) ?></span>
      <div>
        <p class="script-accent" style="margin:0">membre du club</p>
        <h1>Bonjour <em><?= e($first) ?></em></h1>
      </div>
      <form action="<?= e(url('compte/deconnexion')) ?>" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Me déconnecter</button></form>
    </div>

    <?php if (!$customer['email_verified_at']): ?>
      <div class="account-notice">
        <span>✉️ Confirmez votre adresse <strong><?= e($customer['email']) ?></strong> (lien envoyé par e-mail) pour retrouver ici vos commandes passées avant la création du compte.</span>
        <form action="<?= e(url('compte/confirmation')) ?>" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Renvoyer le lien</button></form>
      </div>
    <?php endif; ?>

    <div class="account-grid">
      <div>
        <h2>Mes commandes</h2>
        <?php if ($orders): ?>
          <div class="account-orders">
            <?php foreach ($orders as $order): ?>
              <a class="account-order" href="<?= e(OrderService::trackingUrl($order)) ?>">
                <strong><?= e($order['number']) ?></strong>
                <span class="pill <?= $order['status'] === 'shipped' ? 'pill-rose' : 'pill-blush' ?>"><?= e(order_status_label($order['status'])) ?></span>
                <span class="muted"><?= e(date_fr($order['paid_at'] ?: $order['created_at'], false)) ?> · <?= e(money($order['total'])) ?></span>
                <span class="muted small">Voir le détail →</span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="account-empty">
            <p>Pas encore de commande… votre cheval mérite bien un petit cadeau !</p>
            <a class="btn btn-primary" href="<?= e(url('boutique')) ?>">Découvrir la boutique</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="account-side" id="infos">
        <form action="<?= e(url('compte/informations')) ?>" method="post" class="form-card">
          <?= csrf_field() ?>
          <h2>Mes informations</h2>
          <div class="field"><label for="acc-name">Prénom et nom</label><input id="acc-name" type="text" name="name" maxlength="120" autocomplete="name" value="<?= e($customer['name']) ?>"></div>
          <div class="field"><label>Adresse e-mail</label><input type="email" value="<?= e($customer['email']) ?>" disabled></div>
          <button class="btn btn-ghost btn-sm" type="submit">Enregistrer</button>
        </form>
        <form action="<?= e(url('compte/mot-de-passe')) ?>" method="post" class="form-card">
          <?= csrf_field() ?>
          <h2>Mot de passe</h2>
          <div class="field"><label for="acc-current">Mot de passe actuel</label><input id="acc-current" type="password" name="current_password" required autocomplete="current-password"></div>
          <div class="field"><label for="acc-new">Nouveau mot de passe</label><input id="acc-new" type="password" name="password" required minlength="<?= Account::MIN_PASSWORD ?>" autocomplete="new-password"></div>
          <div class="field"><label for="acc-new2">Confirmez</label><input id="acc-new2" type="password" name="password_confirm" required minlength="<?= Account::MIN_PASSWORD ?>" autocomplete="new-password"></div>
          <button class="btn btn-ghost btn-sm" type="submit">Changer mon mot de passe</button>
        </form>
        <details class="account-danger">
          <summary>Supprimer mon compte</summary>
          <form action="<?= e(url('compte/supprimer')) ?>" method="post" class="form-card" style="margin-top:.8rem">
            <?= csrf_field() ?>
            <p class="small">Votre compte sera définitivement supprimé. Vos commandes restent accessibles avec le lien reçu par e-mail.</p>
            <div class="field"><label for="acc-del">Mot de passe</label><input id="acc-del" type="password" name="current_password" required autocomplete="current-password"></div>
            <button class="btn btn-ghost btn-sm" type="submit">Supprimer définitivement</button>
          </form>
        </details>
      </div>
    </div>
  </div>
</section>
