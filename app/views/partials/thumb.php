<?php
/** @var array $p  @var int $size */
$size ??= 44;
$color = $p['category_color'] ?? $p['supplier_color'] ?? '#8b5cf6';
?>
<div class="thumb" style="width:<?= $size ?>px;height:<?= $size ?>px;background:<?= e($color) ?>">
  <?php if (!empty($p['image'])): ?>
    <img src="<?= e(product_image_url($p['image'])) ?>" alt="" loading="lazy">
  <?php else: ?>
    <?= icon($p['category_icon'] ?? 'box', (int)($size * .5)) ?>
  <?php endif; ?>
</div>
