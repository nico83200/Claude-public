<?php /** @var array $product */ ?>
<article class="product-card">
  <a href="<?= e(url('produit/' . $product['slug'])) ?>" class="product-card-link">
    <div class="product-card-media">
      <img src="<?= e(image_url($product['main_image'])) ?>" alt="<?= e($product['name']) ?>" loading="lazy" width="600" height="600">
      <?php if ($product['personalizable']): ?><span class="badge badge-gold">Personnalisable</span><?php endif; ?>
      <?php if ($product['compare_at_price'] && $product['compare_at_price'] > $product['price']): ?><span class="badge badge-sale">-<?= (int) round(100 - $product['price'] * 100 / $product['compare_at_price']) ?> %</span><?php endif; ?>
    </div>
    <div class="product-card-body">
      <?php if (!empty($product['category_name'])): ?><span class="product-card-cat"><?= e($product['category_name']) ?></span><?php endif; ?>
      <h3><?= e($product['name']) ?></h3>
      <p class="price">
        <?= e(money($product['price'])) ?>
        <?php if ($product['compare_at_price'] && $product['compare_at_price'] > $product['price']): ?><s><?= e(money($product['compare_at_price'])) ?></s><?php endif; ?>
      </p>
    </div>
  </a>
</article>
