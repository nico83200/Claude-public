<?php use App\Services\OrderService; ?>
<section class="section">
  <div class="container narrow center">
    <?php if ($order['status'] === 'pending'): ?>
      <h1>Paiement en cours de validation</h1>
      <p class="lead">Votre paiement est en cours de traitement. Vous recevrez un e-mail de confirmation dès sa validation.</p>
    <?php else: ?>
      <div class="success-icon" aria-hidden="true"><?= icon('heart', 34) ?></div>
      <p class="script-accent">bienvenue au club</p>
      <h1>Merci pour votre <em>commande</em> !</h1>
      <p class="lead">Votre commande <strong><?= e($order['number']) ?></strong> est confirmée. Un e-mail récapitulatif a été envoyé à <strong><?= e($order['email']) ?></strong>.</p>
      <p>Votre article va être imprimé spécialement pour vous, puis expédié sous 2 à 5 jours ouvrés. Vous recevrez le lien de suivi dès l'expédition.</p>
    <?php endif; ?>

    <div class="order-recap">
      <?php foreach ($items as $item): ?>
        <div class="recap-line">
          <span><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?> <span class="muted">(<?= e($item['variant_name']) ?>)</span>
          <?php if ($item['personalization_text']): ?><br><small>✦ <?= e($item['personalization_text']) ?><?= !empty($item['personalization_year']) ? ' · ' . e($item['personalization_year']) : '' ?></small><?php endif; ?></span>
          <span><?= e(money($item['unit_price'] * $item['quantity'])) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="recap-line total"><span>Total payé</span><span><?= e(money($order['total'])) ?></span></div>
    </div>

    <?php if ($order['status'] !== 'pending' && !$customer && $order['email']): ?>
      <?php if ($accountExists): ?>
        <div class="account-offer" id="compte">
          <h2>Retrouvez cette commande dans votre compte</h2>
          <p class="muted">Un compte existe déjà pour <?= e($order['email']) ?> : connectez-vous pour suivre toutes vos commandes.</p>
          <a class="btn btn-ghost" href="<?= e(url('compte/connexion', ['retour' => 'compte'])) ?>">Me connecter</a>
        </div>
      <?php else: ?>
        <form class="account-offer <?= $wantAccount ? 'is-wanted' : '' ?>" id="compte" action="<?= e(url('commande/merci/compte')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
          <h2><?= icon('user', 20) ?> <?= $wantAccount ? 'Finalisez votre compte client' : 'Créez votre compte en 10 secondes' ?></h2>
          <p class="muted">Choisissez simplement un mot de passe : votre compte sera créé avec l'adresse <strong><?= e($order['email']) ?></strong> et cette commande y sera déjà rattachée.</p>
          <div class="field-row">
            <div class="field"><label for="acc-pass">Mot de passe</label><input id="acc-pass" type="password" name="password" required minlength="<?= App\Services\Account::MIN_PASSWORD ?>" autocomplete="new-password" <?= $wantAccount ? 'autofocus' : '' ?>></div>
            <div class="field"><label for="acc-pass2">Confirmez</label><input id="acc-pass2" type="password" name="password_confirm" required minlength="<?= App\Services\Account::MIN_PASSWORD ?>" autocomplete="new-password"></div>
          </div>
          <button class="btn btn-primary" type="submit">Créer mon compte</button>
          <span class="muted small">&nbsp;<?= App\Services\Account::MIN_PASSWORD ?> caractères minimum · sans engagement</span>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <p>
      <a class="btn btn-primary" href="<?= e(OrderService::trackingUrl($order)) ?>">Suivre ma commande</a>
      <a class="btn btn-ghost" href="<?= e(url('boutique')) ?>">Continuer mes achats</a>
    </p>
  </div>
</section>
