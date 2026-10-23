<?php
$colors = [];
$sizes = [];
foreach ($variants as $v) {
    if ($v['color'] !== null && $v['color'] !== '') {
        $colors[$v['color']] = $v['color_hex'];
    }
    if ($v['size'] !== null && $v['size'] !== '') {
        $sizes[$v['size']] = true;
    }
}
$variantsJson = array_map(static fn ($v) => [
    'id'    => (int) $v['id'],
    'color' => $v['color'],
    'size'  => $v['size'],
    'name'  => $v['name'],
    'price' => (int) ($v['price'] ?? $product['price']),
    'priceLabel' => money($v['price'] ?? $product['price']),
    'image' => $v['image'] ? image_url($v['image']) : null,
], $variants);
$first = $variants[0] ?? null;
$images = $product['images_list'] ?: [null];
?>
<section class="section section-tight">
  <div class="container">
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
      <a href="<?= e(url('/')) ?>">Accueil</a> /
      <?php if ($product['category_slug']): ?><a href="<?= e(url('categorie/' . $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a> /<?php endif; ?>
      <span><?= e($product['name']) ?></span>
    </nav>

    <div class="product-layout">
      <div class="gallery">
        <div class="gallery-main" data-preview-stage
          <?php if ($personalization): ?>
            style="--px: <?= (float) $personalization['preview_x'] * 100 ?>%; --py: <?= (float) $personalization['preview_y'] * 100 ?>%; --ps: <?= (float) $personalization['preview_size'] ?>; --pc: <?= e($personalization['preview_color'] ?: $personalization['color']) ?>"
          <?php endif; ?>>
          <img src="<?= e(image_url($images[0])) ?>" alt="<?= e($product['name']) ?>" data-main-image width="800" height="800">
          <?php if ($personalization): ?>
            <span class="preview-text <?= $personalization['font'] === 'sans' ? 'is-sans' : '' ?> <?= $personalization['uppercase'] ? 'is-upper' : '' ?>" data-preview-text aria-hidden="true"></span>
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="thumb <?= $i === 0 ? 'active' : '' ?>" data-thumb="<?= e(image_url($img)) ?>" aria-label="Voir l'image <?= $i + 1 ?>">
                <img src="<?= e(image_url($img)) ?>" alt="" loading="lazy" width="120" height="120">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="product-info">
        <?php if ($product['personalizable']): ?><span class="badge badge-gold badge-inline">✦ Personnalisable</span><?php endif; ?>
        <h1><?= e($product['name']) ?></h1>
        <p class="product-price">
          <span data-price><?= e(money($first['price'] ?? $product['price'])) ?></span>
          <?php if ($product['compare_at_price'] && $product['compare_at_price'] > $product['price']): ?><s><?= e(money($product['compare_at_price'])) ?></s><?php endif; ?>
          <small>TTC</small>
        </p>
        <?php if ($product['short_description']): ?><p class="lead"><?= e($product['short_description']) ?></p><?php endif; ?>

        <?php if (!$variants): ?>
          <p class="flash flash-error">Cet article est momentanément indisponible.</p>
        <?php else: ?>
        <form action="<?= e(url('panier/ajouter')) ?>" method="post" class="buy-form" data-buy-form>
          <?= csrf_field() ?>
          <input type="hidden" name="variant_id" value="<?= (int) $first['id'] ?>" data-variant-input>
          <script type="application/json" data-variants><?= json_encode($variantsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>

          <?php if ($colors): ?>
          <fieldset class="option-group">
            <legend>Couleur : <strong data-selected-color><?= e($first['color']) ?></strong></legend>
            <div class="swatches">
              <?php foreach ($colors as $color => $hex): ?>
                <label class="swatch" title="<?= e($color) ?>">
                  <input type="radio" name="_color" value="<?= e($color) ?>" <?= $color === $first['color'] ? 'checked' : '' ?> data-option="color">
                  <span style="background: <?= e($hex ?: '#ccc') ?>"></span>
                  <span class="sr-only"><?= e($color) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <?php endif; ?>

          <?php if ($sizes): ?>
          <fieldset class="option-group">
            <legend>Taille : <strong data-selected-size><?= e($first['size']) ?></strong></legend>
            <div class="sizes">
              <?php foreach (array_keys($sizes) as $size): ?>
                <label class="size">
                  <input type="radio" name="_size" value="<?= e($size) ?>" <?= $size === $first['size'] ? 'checked' : '' ?> data-option="size">
                  <span><?= e($size) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <?php endif; ?>

          <?php if ($personalization): ?>
          <div class="field personalization-field">
            <label for="personalization"><?= e($personalization['label']) ?><?= empty($personalization['required']) ? ' <span class="muted">(facultatif)</span>' : '' ?></label>
            <input id="personalization" name="personalization" type="text" maxlength="<?= (int) $personalization['max_length'] ?>"
              <?= !empty($personalization['required']) ? 'required' : '' ?> placeholder="Ex. : Tornado" autocomplete="off" data-personalization>
            <small class="muted"><span data-char-count>0</span>/<?= (int) $personalization['max_length'] ?> caractères — lettres, chiffres, espaces, apostrophes et tirets. Vérifiez bien l'orthographe : l'article est imprimé tel quel.</small>
          </div>
          <?php endif; ?>

          <div class="buy-row">
            <div class="qty">
              <label class="sr-only" for="qty">Quantité</label>
              <button type="button" data-qty="-1" aria-label="Diminuer">−</button>
              <input id="qty" type="number" name="qty" value="1" min="1" max="20" inputmode="numeric">
              <button type="button" data-qty="1" aria-label="Augmenter">+</button>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit" data-add-button>Ajouter au panier</button>
          </div>
          <p class="variant-unavailable" data-unavailable hidden>Cette combinaison n'est pas disponible.</p>
        </form>
        <?php endif; ?>

        <ul class="perks">
          <li>Imprimé à la demande en Europe, expédié sous 2 à 5 jours ouvrés</li>
          <li>Livraison offerte dès <?= e(money(setting('shipping_free_threshold'))) ?></li>
          <li>Paiement 100 % sécurisé</li>
        </ul>

        <details class="accordion" open>
          <summary>Description</summary>
          <div class="rich"><?= $product['description'] /* HTML saisi par l'administrateur */ ?></div>
        </details>
        <details class="accordion">
          <summary>Livraison & retours</summary>
          <div class="rich">
            <p>Fabrication sous 2 à 5 jours ouvrés, puis livraison suivie en <?= (int) setting('shipping_standard_min') ?> à <?= (int) setting('shipping_standard_max') ?> jours ouvrés.</p>
            <p>Articles personnalisés : non soumis au droit de rétractation (art. L221-28 du Code de la consommation). Un défaut ? Nous réimprimons gratuitement. <a href="<?= e(url('page/livraison-retours')) ?>">En savoir plus</a></p>
          </div>
        </details>
      </div>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><h2>Vous aimerez aussi</h2></div>
    <div class="product-grid">
      <?php foreach ($related as $item) { partial('product-card', ['product' => $item]); } ?>
    </div>
  </div>
</section>
<?php endif; ?>
