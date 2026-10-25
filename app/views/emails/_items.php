<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;">
<?php foreach ($items as $item): ?>
  <tr>
    <td style="padding:10px 0;border-bottom:1px solid #eee;">
      <strong><?= e($item['product_name']) ?></strong><br>
      <span style="color:#777;font-size:13px;"><?= e(variant_label($item['variant_name'])) ?> · Qté <?= (int) $item['quantity'] ?></span>
      <?php if ($item['personalization_text']): ?><br><span style="color:#5a2a3a;font-size:13px;">✦ Personnalisation : <?= e($item['personalization_text']) ?></span><?php endif; ?>
      <?php if (!empty($item['personalization_year'])): ?><br><span style="color:#5a2a3a;font-size:13px;">✦ Année : <?= e($item['personalization_year']) ?></span><?php endif; ?>
    </td>
    <td style="padding:10px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap;"><?= e(money($item['unit_price'] * $item['quantity'])) ?></td>
  </tr>
<?php endforeach; ?>
<?php if (isset($order)): ?>
  <tr><td style="padding:6px 0;color:#777;">Livraison</td><td style="text-align:right;color:#777;"><?= $order['shipping'] ? e(money($order['shipping'])) : 'Offerte' ?></td></tr>
  <?php if ($order['discount']): ?><tr><td style="padding:6px 0;color:#777;">Réduction</td><td style="text-align:right;color:#777;">−<?= e(money($order['discount'])) ?></td></tr><?php endif; ?>
  <tr><td style="padding:10px 0;font-size:17px;"><strong>Total TTC</strong></td><td style="text-align:right;font-size:17px;"><strong><?= e(money($order['total'])) ?></strong></td></tr>
<?php endif; ?>
</table>
