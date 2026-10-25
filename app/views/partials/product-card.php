<?php /** @var array $product */ ?>
<article class="product-card">
  <a href="<?= e(url('produit/' . $product['slug'])) ?>" class="product-card-link">
    <div class="product-card-media">
      <img <?= img_attrs($product['main_image'], [400, 600, 800], '(max-width: 640px) 50vw, (max-width: 1100px) 33vw, 300px') ?> alt="<?= e($product['name']) ?>" loading="lazy" decoding="async" width="600" height="750">
      <?php if ($product['hover_image']): ?><img class="img-hover" <?= img_attrs($product['hover_image'], [400, 600, 800], '(max-width: 640px) 50vw, (max-width: 1100px) 33vw, 300px') ?> alt="" loading="lazy" decoding="async" width="600" height="750"><?php endif; ?>
      <div class="badges">
        <span><?php if ($product['badge']): ?><span class="pill <?= $product['badge'] === 'Best-seller' ? 'pill-rose' : '' ?>"><?= e($product['badge']) ?></span><?php endif; ?></span>
        <?php if ($product['discount']): ?><span class="pill pill-ink">-<?= (int) $product['discount'] ?> %</span><?php endif; ?>
      </div>
      <?php if ($product['personalizable']): ?><span class="pill perso-tag"><?= icon('sparkle', 13) ?> À personnaliser</span><?php endif; ?>
      <span class="card-cta" aria-hidden="true"><?= icon('arrow', 18) ?></span>
    </div>
    <div class="product-card-body">
      <?php if (!empty($product['category_name'])): ?><span class="product-card-cat"><?= e($product['category_name']) ?></span><?php endif; ?>
      <h3><?= e($product['name']) ?></h3>
      <?php if ($product['rating_count'] > 0): ?><div class="rating-row"><?= stars($product['rating_avg'], $product['rating_count']) ?></div><?php endif; ?>
      <div class="price-row">
        <p class="price <?= $product['discount'] ? 'is-sale' : '' ?>"><?= e(money($product['price'])) ?></p>
        <?php if ($product['discount']): ?><span class="price-old"><?= e(money($product['compare_at_price'])) ?></span><?php endif; ?>
      </div>
    </div>
  </a>
</article>
