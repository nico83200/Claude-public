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
    'id'         => (int) $v['id'],
    'color'      => $v['color'],
    'size'       => $v['size'],
    'price'      => (int) ($v['price'] ?? $product['price']),
    'priceLabel' => money($v['price'] ?? $product['price']),
    'image'      => $v['image'] ? image_url($v['image']) : null,
], $variants);
$first = $variants[0] ?? null;
$images = $product['images_list'] ?: [null];
$cfg = $personalization;
$mode = $cfg['mode'] ?? null;
$needsPhoto = $cfg && in_array($mode, ['photo', 'portrait'], true);
$price = (int) ($first['price'] ?? $product['price']);
$saving = $product['discount'] ? (int) $product['compare_at_price'] - (int) $product['price'] : 0;
$stageStyle = '';
if ($cfg) {
    $stageStyle = sprintf(
        '--px:%s%%;--py:%s%%;--ps:%s;--pc:%s;--phx:%s%%;--phy:%s%%;--phw:%s%%;--phh:%s%%;--arch-v:%s%%',
        (float) $cfg['preview_x'] * 100, (float) $cfg['preview_y'] * 100, (float) $cfg['preview_size'], e($cfg['preview_color'] ?: $cfg['color']),
        (float) $cfg['preview_photo_x'] * 100, (float) $cfg['preview_photo_y'] * 100, (float) $cfg['preview_photo_w'] * 100, (float) $cfg['preview_photo_h'] * 100,
        round(min(100, (float) $cfg['preview_photo_w'] / 2 / max(.01, (float) $cfg['preview_photo_h']) * 100), 2)
    );
}
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
        <div class="gallery-main" data-preview-stage <?= $cfg ? 'style="' . $stageStyle . '"' : '' ?>
             <?= $cfg && $cfg['preview_image'] ? 'data-preview-base="' . e(image_url($cfg['preview_image'])) . '"' : '' ?>>
          <?php if ($product['badge']): ?><span class="pill <?= $product['badge'] === 'Best-seller' ? 'pill-rose' : '' ?>"><?= e($product['badge']) ?></span><?php endif; ?>
          <img src="<?= e(image_url($images[0])) ?>" alt="<?= e($product['name']) ?>" data-main-image width="800" height="800">
          <?php if ($cfg && $mode === 'photo'): ?>
            <span class="preview-photo shape-<?= e($cfg['photo_shape']) ?>" data-preview-photo hidden></span>
          <?php endif; ?>
          <?php if ($cfg): ?>
            <span class="preview-text font-<?= e($cfg['font']) ?> <?= $cfg['uppercase'] && $cfg['font'] !== 'script' ? 'is-upper' : '' ?>" data-preview-text aria-hidden="true"></span>
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
        <?php if ($product['category_name']): ?><p class="eyebrow"><?= e($product['category_name']) ?></p><?php endif; ?>
        <h1><?= e($product['name']) ?></h1>
        <?php if ($reviewSummary['count'] > 0): ?>
          <a class="product-rating" href="#avis"><?= stars($reviewSummary['avg']) ?> <span><?= e(number_format($reviewSummary['avg'], 1, ',', '')) ?>/5 · <?= (int) $reviewSummary['count'] ?> avis vérifiés</span></a>
        <?php endif; ?>
        <div class="product-price">
          <span class="now <?= $saving ? 'is-sale' : '' ?>" data-price><?= e(money($price)) ?></span>
          <?php if ($saving): ?><s><?= e(money($product['compare_at_price'])) ?></s><span class="pill pill-blush">Vous économisez <?= e(money($saving)) ?></span><?php endif; ?>
          <small>TTC</small>
        </div>
        <?php if (setting('show_installments') === '1' && $price >= 3500): ?>
          <p class="installments">ou <strong>3 × <?= e(money((int) ceil($price / 3))) ?></strong> sans frais avec Klarna</p>
        <?php endif; ?>
        <?php if ($product['short_description']): ?><p class="lead"><?= e($product['short_description']) ?></p><?php endif; ?>

        <?php if (!$variants): ?>
          <p class="flash flash-error">Cet article est momentanément indisponible.</p>
        <?php else: ?>
        <form action="<?= e(url('panier/ajouter')) ?>" method="post" class="buy-box" data-buy-form <?= $needsPhoto ? 'enctype="multipart/form-data"' : '' ?>>
          <?= csrf_field() ?>
          <input type="hidden" name="variant_id" value="<?= (int) $first['id'] ?>" data-variant-input>
          <script type="application/json" data-variants><?= json_encode($variantsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>

          <?php if ($colors): ?>
          <fieldset class="option-group">
            <legend class="option-head"><span>Couleur : <strong data-selected-color><?= e($first['color']) ?></strong></span></legend>
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

          <?php if (count($sizes) === 1): ?>
            <p class="single-option"><?= $sizeGuide ? 'Taille' : 'Format' ?> : <strong><?= e($first['size']) ?></strong></p>
          <?php elseif ($sizes): ?>
          <fieldset class="option-group">
            <div class="option-head">
              <legend><?= $sizeGuide || count(array_intersect(array_keys($sizes), ['XS', 'S', 'M', 'L', 'XL'])) ? 'Taille' : 'Format' ?> : <strong data-selected-size><?= e($first['size']) ?></strong></legend>
              <?php if ($sizeGuide): ?><button type="button" class="link-button" data-open-dialog="size-guide"><?= icon('ruler', 15) ?> Guide des tailles</button><?php endif; ?>
            </div>
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

          <?php if ($cfg): ?>
          <div class="perso-box">
            <p class="perso-title"><?= icon('sparkle', 16) ?> Votre personnalisation <span class="pill pill-blush">Offerte</span></p>
            <?php if ($needsPhoto): ?>
              <label class="photo-drop" data-photo-drop>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required data-photo-input>
                <span class="photo-thumb" data-photo-thumb hidden></span>
                <span class="photo-text">
                  <strong data-photo-label><?= $mode === 'portrait' ? 'Ajoutez la photo de votre cheval' : 'Ajoutez sa plus belle photo' ?></strong>
                  <small>JPG ou PNG · tête bien visible · 20 Mo max.</small>
                </span>
              </label>
              <?php if ($mode === 'portrait'): ?>
                <p class="perso-note"><?= icon('brush', 15) ?> Notre illustratrice dessine le portrait à la main d'après votre photo (<?= (int) $cfg['extra_days'] ?> jours ouvrés de création).</p>
              <?php endif; ?>
            <?php endif; ?>
            <div class="field">
              <label for="personalization"><?= e($cfg['label']) ?><?= empty($cfg['required']) ? ' <span class="muted">(facultatif)</span>' : '' ?></label>
              <input id="personalization" name="personalization" type="text" maxlength="<?= (int) $cfg['max_length'] ?>"
                <?= !empty($cfg['required']) ? 'required' : '' ?> placeholder="Ex. : Tornado" autocomplete="off" data-personalization>
              <small><span data-char-count>0</span>/<?= (int) $cfg['max_length'] ?> caractères · imprimé exactement comme saisi, vérifiez l'orthographe ♡</small>
            </div>
          </div>
          <?php endif; ?>

          <div class="buy-row">
            <div class="qty">
              <label class="sr-only" for="qty">Quantité</label>
              <button type="button" data-qty="-1" aria-label="Diminuer">−</button>
              <input id="qty" type="number" name="qty" value="1" min="1" max="20" inputmode="numeric">
              <button type="button" data-qty="1" aria-label="Augmenter">+</button>
            </div>
            <button class="btn btn-primary btn-lg" type="submit" data-add-button>Ajouter au panier</button>
          </div>
          <p class="variant-unavailable" data-unavailable hidden>Cette combinaison n'est pas disponible.</p>
          <p class="form-error" data-form-error role="alert" hidden></p>
        </form>
        <?php endif; ?>

        <div class="delivery-box">
          <?= icon('calendar', 20) ?>
          <span>Commandez aujourd'hui, recevez-le entre le <strong><?= e($delivery['from']) ?></strong> et le <strong><?= e($delivery['to']) ?></strong><br>
          <span class="muted">Livraison offerte dès <?= e(money(setting('shipping_free_threshold'))) ?> · suivi par e-mail</span></span>
        </div>

        <div class="trust-row">
          <div><?= icon('leaf', 20) ?>Imprimé pour vous en Europe</div>
          <div><?= icon('lock', 20) ?>Paiement 100 % sécurisé</div>
          <div><?= icon('refresh', 20) ?>Un défaut ? On réimprime</div>
        </div>
        <p class="pay-methods" aria-label="Moyens de paiement"><span>CB</span><span>VISA</span><span>Mastercard</span><span>Apple Pay</span><span>Google Pay</span></p>

        <details class="accordion" open>
          <summary>Description</summary>
          <div class="rich"><?= $product['description'] /* HTML saisi par l'administrateur */ ?></div>
        </details>
        <?php if ($cfg): ?>
        <details class="accordion">
          <summary>Comment se passe la personnalisation ?</summary>
          <div class="rich">
            <?php if ($mode === 'portrait'): ?>
              <p>Ajoutez une photo nette de votre cheval : notre illustratrice réalise son portrait au trait fin, dans le style Crinière Club, puis il part à l'impression. Comptez <?= (int) $cfg['extra_days'] ?> jours ouvrés de création.</p>
            <?php elseif ($mode === 'photo'): ?>
              <p>Votre photo est cadrée automatiquement dans notre arche signature, avec le nom de votre cheval si vous le souhaitez. Choisissez une photo nette où sa tête est bien visible.</p>
            <?php else: ?>
              <p>Le nom saisi est imprimé tel quel, dans la police présentée dans l'aperçu. Lettres, chiffres, espaces, apostrophes et tirets sont acceptés.</p>
            <?php endif; ?>
            <p>Les articles personnalisés sont fabriqués selon vos indications : ils ne sont pas soumis au droit de rétractation (art. L221-28 du Code de la consommation). En cas de défaut, nous les réimprimons gratuitement.</p>
          </div>
        </details>
        <?php endif; ?>
        <details class="accordion">
          <summary>Livraison & retours</summary>
          <div class="rich">
            <p>Fabrication sous <?= (int) setting('production_min') ?> à <?= (int) setting('production_max') ?> jours ouvrés, puis livraison suivie en <?= (int) setting('shipping_standard_min') ?> à <?= (int) setting('shipping_standard_max') ?> jours ouvrés. Express disponible au paiement.</p>
            <p><a href="<?= e(url('page/livraison-retours')) ?>">Toutes les conditions de livraison et de retour</a></p>
          </div>
        </details>
      </div>
    </div>
  </div>
</section>

<?php if (App\Services\Reviews::enabled()): ?>
<section class="section section-blush" id="avis">
  <div class="container">
    <div class="section-head"><div><p class="script-accent">avis vérifiés</p><h2>Elles l'ont <em>adopté</em></h2></div></div>
    <?php if ($reviewSummary['count'] > 0): ?>
      <div class="reviews-layout">
        <aside class="reviews-summary">
          <div class="reviews-score"><?= e(number_format($reviewSummary['avg'], 1, ',', '')) ?></div>
          <?= stars($reviewSummary['avg'], null, 'stars-lg') ?>
          <p class="muted small"><?= (int) $reviewSummary['count'] ?> avis vérifiés</p>
          <div class="dist">
            <?php foreach ($reviewSummary['dist'] as $note => $n): ?>
              <div><span><?= $note ?> ★</span><span class="bar"><span style="width: <?= $reviewSummary['count'] ? (int) round($n * 100 / $reviewSummary['count']) : 0 ?>%"></span></span><span><?= $n ?></span></div>
            <?php endforeach; ?>
          </div>
        </aside>
        <div>
          <?php foreach ($reviews as $r): ?>
            <article class="review">
              <?= stars((float) $r['rating']) ?>
              <?php if ($r['title']): ?><h3><?= e($r['title']) ?></h3><?php endif; ?>
              <?php if ($r['body']): ?><p><?= nl2br(e($r['body'])) ?></p><?php endif; ?>
              <div class="review-meta"><strong><?= e($r['author_name']) ?></strong><span><?= e(date_fr($r['created_at'], false)) ?></span><?php if ($r['verified']): ?><span class="verified"><?= icon('check', 12) ?> Achat vérifié</span><?php endif; ?></div>
            </article>
          <?php endforeach; ?>
          <p class="reviews-note">Avis vérifiés : seules les clientes ayant reçu leur commande peuvent noter un article, via un lien personnel envoyé après la livraison. Les avis sont publiés par ordre chronologique et modérés uniquement pour écarter les contenus illicites ; aucune contrepartie n'est accordée en échange d'un avis.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="empty">
        <p class="script-accent">bientôt vos avis</p>
        <p>Cet article n'a pas encore d'avis. Après votre commande, vous recevrez une invitation pour partager le vôtre.</p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><div><p class="script-accent">à associer</p><h2>Vous allez <em>adorer aussi</em></h2></div></div>
    <div class="product-grid">
      <?php foreach ($related as $item) { partial('product-card', ['product' => $item]); } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($variants): ?>
<div class="sticky-buy" data-sticky-buy aria-hidden="true">
  <div class="sticky-buy-info"><strong><?= e($product['name']) ?></strong><span data-price><?= e(money($price)) ?></span></div>
  <button class="btn btn-primary" type="button" data-sticky-add tabindex="-1"><?= $cfg ? 'Personnaliser' : 'Ajouter au panier' ?></button>
</div>
<?php endif; ?>

<?php if ($sizeGuide): ?>
<dialog class="modal size-modal" id="size-guide" aria-labelledby="size-guide-title">
  <button class="icon-btn modal-close" type="button" data-modal-close aria-label="Fermer"><?= icon('close', 20) ?></button>
  <div class="modal-body rich">
    <h2 id="size-guide-title"><?= e($sizeGuide['title']) ?></h2>
    <?= $sizeGuide['content'] /* page administrée */ ?>
  </div>
</dialog>
<?php endif; ?>
