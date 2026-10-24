<?php if ((int) setting('shipping_free_threshold') > 0): ?>
  <?php if ($remaining > 0): ?>
    <div class="ship-progress">
      <p><?= icon('truck', 18) ?> <span>Plus que <strong><?= e(money($remaining)) ?></strong> pour la livraison offerte</span></p>
      <div class="progress" role="progressbar" aria-valuenow="<?= (int) $progress ?>" aria-valuemin="0" aria-valuemax="100"><span style="width: <?= (int) $progress ?>%"></span></div>
    </div>
  <?php else: ?>
    <div class="ship-progress done"><p><?= icon('check', 18) ?> Livraison standard offerte ♡</p></div>
  <?php endif; ?>
<?php endif; ?>
