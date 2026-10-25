<?php /** Contenu du mini-panier (chargé en AJAX) */ ?>
<div class="drawer-body">
  <?php if (!empty($justAdded)): ?><p class="drawer-added"><?= icon('check', 18) ?> Ajouté à ton panier</p><?php endif; ?>
  <?php if (!$items): ?>
    <div class="drawer-empty">
      <p class="script-accent">Ton panier est vide</p>
      <p class="muted">Et si tu commençais par nos pièces du Club ?</p>
      <a class="btn btn-primary" href="<?= e(url('boutique')) ?>">Découvrir la boutique</a>
    </div>
  <?php else: ?>
    <?php partial('shipping-progress', ['remaining' => $remaining, 'progress' => $progress]); ?>
    <div class="mt">
      <?php foreach ($items as $item) { partial('line-item', ['item' => $item, 'compact' => true]); } ?>
    </div>
    <?php if ($boosters): ?>
      <div class="boosters">
        <h3>Pour profiter de la livraison offerte</h3>
        <div class="booster-list"><?php foreach ($boosters as $b) { partial('booster', ['product' => $b]); } ?></div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php if ($items): ?>
<div class="drawer-foot">
  <div class="total-line"><span>Sous-total</span><span><?= e(money($subtotal)) ?></span></div>
  <p class="muted small">Livraison <?= $shipping[0]['price'] === 0 ? '<strong>offerte</strong>' : 'dès ' . e(money($shipping[0]['price'])) ?> · codes promo à l'étape suivante</p>
  <div class="drawer-actions">
    <a class="btn btn-ghost btn-lg" href="<?= e(url('panier')) ?>"><?= icon('bag', 18) ?> Mon panier</a>
    <a class="btn btn-primary btn-lg" href="<?= e(url('panier')) ?>#paiement">Commander</a>
  </div>
  <p class="payment-note"><?= icon('lock', 14) ?> Paiement 100 % sécurisé</p>
</div>
<?php endif; ?>
