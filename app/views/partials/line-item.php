<?php /** @var array $item */ $compact = !empty($compact); ?>
<div class="line-item">
  <a href="<?= e(url('produit/' . $item['slug'])) ?>" class="line-item-media"><img src="<?= e(!empty($item['portrait']) ? url('portrait-ia/' . $item['portrait'] . '/apercu') : thumb($item['image'], 160)) ?>" alt="" width="96" height="96"></a>
  <div class="line-item-info">
    <a href="<?= e(url('produit/' . $item['slug'])) ?>" class="line-item-name"><?= e($item['product_name']) ?></a>
    <span class="muted small"><?= e(variant_label($item['variant_name'])) ?><?= $compact ? ' · Qté ' . (int) $item['qty'] : '' ?></span>
    <?php if ($item['text'] !== ''): ?><span class="personalized-tag"><?= icon('sparkle', 13) ?> <?= e($item['personalization_label']) ?> : <strong><?= e($item['text']) ?></strong></span><?php endif; ?>
    <?php if (!empty($item['year'])): ?><span class="personalized-tag">Année : <strong><?= e($item['year']) ?></strong></span><?php endif; ?>
    <?php if (!empty($item['portrait'])): ?>
      <span class="personalized-tag"><?= icon('brush', 13) ?> Votre portrait « Encre &amp; Poudre »</span>
    <?php endif; ?>
    <?php if (!empty($item['photo'])): ?>
      <span class="personalized-tag photo-tag"><img src="<?= e(url('panier/photo/' . $item['photo'])) ?>" alt="Votre photo" width="28" height="28"> <?= $item['mode'] === 'portrait' ? 'Photo pour le portrait' : 'Votre photo' ?></span>
    <?php endif; ?>
    <?php if (!$compact): ?><span class="muted small"><?= e(money($item['unit_price'])) ?> l'unité</span><?php endif; ?>
  </div>
  <div class="line-item-side">
    <?php if (!$compact): ?>
      <label class="sr-only" for="qty-<?= e($item['key']) ?>">Quantité</label>
      <input id="qty-<?= e($item['key']) ?>" type="number" min="0" max="20" name="qty[<?= e($item['key']) ?>]" value="<?= (int) $item['qty'] ?>" form="cart-form" data-autosubmit>
    <?php endif; ?>
    <span class="line-total"><?= e(money($item['line_total'])) ?></span>
    <form action="<?= e(url('panier/supprimer')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($item['key']) ?>"><button class="link-button small muted" type="submit">Retirer</button></form>
  </div>
</div>
