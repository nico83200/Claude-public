<?php
/** @var array $p @var array $favIds @var array $deadlinesBySupplier */
$isFav = in_array((int)$p['id'], $favIds ?? [], true);
$price = effective_price($p);
$hasDeal = $p['negotiated_price'] !== null && (float)$p['negotiated_price'] > 0 && (float)$p['negotiated_price'] < (float)$p['catalog_price'];
$dl = $deadlinesBySupplier[$p['supplier_id']] ?? null;
?>
<article class="product">
  <div class="product-img" style="<?= empty($p['image']) ? 'background:linear-gradient(135deg,' . e($p['category_color'] ?? '#8b5cf6') . ',' . e($p['supplier_color'] ?? '#6366f1') . ')' : '' ?>">
    <?php if (!empty($p['image'])): ?>
      <img src="<?= e(product_image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <?php else: ?>
      <div class="product-ph"><?= icon($p['category_icon'] ?? 'box', 56) ?></div>
    <?php endif; ?>
    <?php if (!empty($p['category_name'])): ?><span class="badge product-cat" style="background:rgba(255,255,255,.92);color:#333"><?= e($p['category_name']) ?></span><?php endif; ?>
    <button type="button" class="product-fav <?= $isFav ? 'on' : '' ?>" data-fav="<?= (int)$p['id'] ?>" title="Favori"><?= icon('heart', 17) ?></button>
  </div>
  <div class="product-body">
    <a class="product-name" href="<?= url('product', ['id' => $p['id']]) ?>"><?= e($p['name']) ?></a>
    <div class="product-meta">
      <span class="dot" style="background:<?= e($p['supplier_color']) ?>;width:8px;height:8px"></span> <?= e($p['supplier_name']) ?>
      <?php if ($p['reference']): ?> · Réf. <?= e($p['reference']) ?><?php endif; ?>
    </div>
    <?php if ($p['unit']): ?><div class="product-meta"><?= icon('box', 14) ?> <?= e($p['unit']) ?></div><?php endif; ?>
    <?php if ($dl): $cd = countdown($dl); ?><div><span class="deadline-pill" title="Date limite de commande fournisseur"><?= icon('clock', 13) ?> Avant le <?= date_fr($dl) ?> (<?= e($cd['label']) ?>)</span></div><?php endif; ?>
    <div class="spacer"></div>
    <?php if (show_prices()): ?>
      <div class="product-price"><?= money($price) ?><?php if ($hasDeal): ?><s><?= money($p['catalog_price']) ?></s><?php endif; ?> <small class="muted" style="font-weight:500;font-size:.75rem">HT</small></div>
    <?php endif; ?>
  </div>
  <form class="product-foot" method="post" action="<?= url('cart/add') ?>" data-add-cart>
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
    <input class="qty-input" type="number" name="qty" min="1" max="9999" value="<?= max(1, (int)$p['min_qty']) ?>" aria-label="Quantité">
    <button class="btn btn-primary btn-sm" type="submit"><?= icon('plus', 16) ?> Ajouter</button>
  </form>
</article>
