<?php
/**
 * Bloc d'achat d'un produit (galerie, options, personnalisation, réassurance, détails),
 * partagé par la fiche produit et la page dédiée au portrait aquarelle.
 * @var array $product @var array $variants @var ?array $personalization @var array $reviewSummary
 * @var ?array $sizeGuide @var array $delivery @var string $titleTag (h1 par défaut)
 */
$titleTag = ($titleTag ?? 'h1') === 'h2' ? 'h2' : 'h1';
$previewFirst = !empty($previewFirst); // portrait IA : aperçu encadré affiché d'emblée
$detailsOpen = $detailsOpen ?? true;
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
    'colorHex'   => $v['color_hex'],
], $variants);
$first = $variants[0] ?? null;
$images = $product['images_list'] ?: [null];
$cfg = $personalization;
$mode = $cfg['mode'] ?? null;
$needsPhoto = $cfg && in_array($mode, ['photo', 'portrait'], true);
$isAi = $cfg && $mode === 'ai_portrait' && App\Services\AiPortrait::enabled();
if ($cfg && $mode === 'ai_portrait' && !$isAi) {
    $variants = []; // migration 006 en attente : article indisponible
}
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
    <div class="product-layout">
      <div class="gallery">
        <div class="gallery-main" data-preview-stage <?= $cfg ? 'style="' . $stageStyle . '"' : '' ?>
             <?= $cfg && $cfg['preview_image'] ? 'data-preview-base="' . e(image_url($cfg['preview_image'])) . '"' : '' ?>>
          <?php if ($product['badge']): ?><span class="pill <?= $product['badge'] === 'Best-seller' ? 'pill-rose' : '' ?>"><?= e($product['badge']) ?></span><?php endif; ?>
          <div class="gallery-zoom" data-zoom-layer>
          <img <?= img_attrs($images[0] ?? null, [600, 800, 1200], '(max-width: 960px) 100vw, 640px') ?> alt="<?= e($product['name']) ?>" data-main-image data-full="<?= e(image_url($images[0] ?? null)) ?>" width="800" height="800" fetchpriority="high">
          <?php if ($cfg && $mode === 'photo'): ?>
            <span class="preview-photo shape-<?= e($cfg['photo_shape']) ?>" data-preview-photo hidden></span>
          <?php endif; ?>
          <?php if ($cfg && !$isAi): ?>
            <span class="preview-text font-<?= e($cfg['font']) ?> <?= $cfg['uppercase'] && $cfg['font'] !== 'script' ? 'is-upper' : '' ?>" data-preview-text aria-hidden="true"></span>
          <?php endif; ?>
          <?php if ($isAi): ?>
            <?php partial('ai-portrait-stage', ['firstColor' => $first['color_hex'] ?? null, 'visible' => $previewFirst]); ?>
          <?php endif; ?>
          </div>
          <span class="zoom-hint" aria-hidden="true"><?= icon('search', 15) ?> <span class="hint-mouse">Survolez pour zoomer</span><span class="hint-touch"><?= $cfg ? 'Glissez pour voir les photos' : 'Touchez pour agrandir' ?></span></span>
        </div>
        <?php if (count($images) > 1 || $isAi): ?>
          <div class="gallery-thumbs">
            <?php if ($isAi): ?>
              <button type="button" class="thumb thumb-preview <?= $previewFirst ? 'active' : '' ?>" data-ai-show aria-label="Voir l'aperçu de votre portrait"><?= icon('sparkle', 20) ?><span>Aperçu</span></button>
            <?php endif; ?>
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="thumb <?= $i === 0 && !($isAi && $previewFirst) ? 'active' : '' ?>" data-thumb="<?= e(thumb($img, 800)) ?>" data-thumb-set="<?= e(thumb($img, 600) . ' 600w, ' . thumb($img, 800) . ' 800w, ' . thumb($img, 1200) . ' 1200w') ?>" data-full="<?= e(image_url($img)) ?>" aria-label="Voir l'image <?= $i + 1 ?>">
                <img src="<?= e(thumb($img, 160)) ?>" alt="" loading="lazy" width="120" height="120">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!$cfg): ?>
      <dialog class="lightbox" data-lightbox aria-label="Photo agrandie">
        <div class="lightbox-scroll" data-lightbox-scroll><img src="" alt="<?= e($product['name']) ?>" data-lightbox-img></div>
        <button type="button" class="lightbox-btn lightbox-close" data-lightbox-close aria-label="Fermer"><?= icon('close', 22) ?></button>
        <button type="button" class="lightbox-btn lightbox-zoom" data-lightbox-zoom aria-label="Zoomer"><?= icon('search', 20) ?></button>
        <?php if (count($images) > 1): ?>
          <button type="button" class="lightbox-btn lightbox-prev" data-lightbox-step="-1" aria-label="Photo précédente">‹</button>
          <button type="button" class="lightbox-btn lightbox-next" data-lightbox-step="1" aria-label="Photo suivante">›</button>
        <?php endif; ?>
      </dialog>
      <?php endif; ?>

      <div class="product-info">
        <?php if ($product['category_name']): ?><p class="eyebrow"><?= e($product['category_name']) ?></p><?php endif; ?>
        <<?= $titleTag ?> class="product-title"><?= e($product['name']) ?></<?= $titleTag ?>>
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

          <?php if ($isAi): ?>
            <?php partial('ai-portrait-box', ['cfg' => $cfg, 'product' => $product]); ?>
          <?php elseif ($cfg): ?>
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

        <details class="accordion" <?= $detailsOpen ? 'open' : '' ?>>
          <summary>Description</summary>
          <div class="rich"><?= rich_text($product['description']) /* texte mis en forme ou HTML nettoyé */ ?></div>
        </details>
        <?php if ($cfg): ?>
        <details class="accordion">
          <summary>Comment se passe la personnalisation ?</summary>
          <div class="rich">
            <?php if ($isAi): ?>
              <p>Ajoutez une photo nette de votre cheval puis cliquez sur « Créer mon portrait » : notre IA le redessine en quelques secondes dans le style « Encre & Poudre », un trait fin bordeaux rehaussé d'aquarelle aux tons poudrés. Saisissez son nom et, si vous le souhaitez, son année de naissance : ils s'inscrivent sous le portrait, comme sur l'aperçu.</p>
              <p>Vous pouvez créer plusieurs versions et garder votre préférée. L'aperçu est volontairement réduit et filigrané : votre portrait est imprimé en haute définition, sans filigrane, sur papier d'art mat, puis encadré.</p>
            <?php elseif ($mode === 'portrait'): ?>
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
