<a class="booster" href="<?= e(url('produit/' . $product['slug'])) ?>">
  <img src="<?= e(thumb($product['main_image'], 160)) ?>" alt="" width="64" height="64" loading="lazy">
  <span><?= e($product['name']) ?><strong><?= e(money($product['price'])) ?></strong></span>
</a>
