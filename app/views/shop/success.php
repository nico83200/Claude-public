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
          <?php if ($item['personalization_text']): ?><br><small>✦ <?= e($item['personalization_text']) ?></small><?php endif; ?></span>
          <span><?= e(money($item['unit_price'] * $item['quantity'])) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="recap-line total"><span>Total payé</span><span><?= e(money($order['total'])) ?></span></div>
    </div>

    <p>
      <a class="btn btn-primary" href="<?= e(OrderService::trackingUrl($order)) ?>">Suivre ma commande</a>
      <a class="btn btn-ghost" href="<?= e(url('boutique')) ?>">Continuer mes achats</a>
    </p>
  </div>
</section>
