<?php
$steps = ['paid' => 'Commande confirmée', 'in_production' => 'En fabrication', 'shipped' => 'Expédiée'];
$reached = ['paid' => 1, 'in_production' => 2, 'shipped' => 3][$order['status']] ?? 0;
?>
<section class="section">
  <div class="container narrow">
    <h1>Commande <?= e($order['number']) ?></h1>
    <p class="muted">Passée le <?= e(date_fr($order['paid_at'] ?? $order['created_at'])) ?> · Statut : <strong><?= e(order_status_label($order['status'])) ?></strong></p>

    <?php if ($reached): ?>
    <ol class="timeline">
      <?php $i = 0; foreach ($steps as $key => $label): $i++; ?>
        <li class="<?= $i <= $reached ? 'done' : '' ?>"><span><?= $i ?></span><?= e($label) ?></li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <?php if ($order['tracking_number']): ?>
      <div class="flash flash-info">
        Colis expédié<?= $order['carrier'] ? ' via ' . e($order['carrier']) : '' ?> — n° de suivi <strong><?= e($order['tracking_number']) ?></strong>
        <?php if ($order['tracking_url']): ?> · <a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener">Suivre le colis</a><?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="order-recap">
      <?php foreach ($items as $item): ?>
        <div class="recap-line">
          <span><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?> <span class="muted">(<?= e(variant_label($item['variant_name'])) ?>)</span>
          <?php if ($item['personalization_text']): ?><br><small>✦ <?= e($item['personalization_text']) ?><?= !empty($item['personalization_year']) ? ' · ' . e($item['personalization_year']) : '' ?></small><?php endif; ?></span>
          <span><?= e(money($item['unit_price'] * $item['quantity'])) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="recap-line"><span>Livraison</span><span><?= $order['shipping'] ? e(money($order['shipping'])) : 'Offerte' ?></span></div>
      <?php if ($order['discount']): ?><div class="recap-line"><span>Réduction</span><span>−<?= e(money($order['discount'])) ?></span></div><?php endif; ?>
      <div class="recap-line total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
    </div>

    <?php if ($address): ?>
      <h2>Adresse de livraison</h2>
      <p><?= e($address['name']) ?><br><?= e($address['line1']) ?><?= $address['line2'] ? '<br>' . e($address['line2']) : '' ?><br><?= e($address['postal_code'] . ' ' . $address['city']) ?><br><?= e($address['country']) ?></p>
    <?php endif; ?>
    <p>Une question ? <a href="<?= e(url('contact')) ?>">Contacte-nous</a> en précisant le numéro <?= e($order['number']) ?>.</p>
  </div>
</section>
