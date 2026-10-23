<section class="page-head">
  <div class="container"><h1>Mon panier</h1></div>
</section>

<section class="section section-tight">
  <div class="container">
    <?php if (isset($_GET['annule'])): ?>
      <div class="flash flash-info">Paiement annulé : votre panier a été conservé.</div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="empty">
        <p>Votre panier est vide… pour l'instant !</p>
        <a class="btn btn-primary" href="<?= e(url('boutique')) ?>">Découvrir la boutique</a>
      </div>
    <?php else: ?>
    <div class="cart-layout">
      <form action="<?= e(url('panier/modifier')) ?>" method="post" class="cart-items" id="cart-form">
        <?= csrf_field() ?>
        <?php foreach ($items as $item): ?>
          <div class="cart-item">
            <a href="<?= e(url('produit/' . $item['slug'])) ?>" class="cart-item-media"><img src="<?= e(image_url($item['image'])) ?>" alt="" width="96" height="96"></a>
            <div class="cart-item-info">
              <a href="<?= e(url('produit/' . $item['slug'])) ?>" class="cart-item-name"><?= e($item['product_name']) ?></a>
              <span class="muted"><?= e($item['variant_name']) ?></span>
              <?php if ($item['text'] !== ''): ?><span class="personalized-tag">✦ <?= e($item['personalization_label']) ?> : <strong><?= e($item['text']) ?></strong></span><?php endif; ?>
              <span class="muted small"><?= e(money($item['unit_price'])) ?> l'unité</span>
            </div>
            <div class="cart-item-qty">
              <label class="sr-only" for="qty-<?= e($item['key']) ?>">Quantité</label>
              <input id="qty-<?= e($item['key']) ?>" type="number" min="0" max="20" name="qty[<?= e($item['key']) ?>]" value="<?= (int) $item['qty'] ?>" data-autosubmit>
              <button class="link-button small" type="submit" form="remove-<?= e($item['key']) ?>">Retirer</button>
            </div>
            <div class="cart-item-total"><?= e(money($item['line_total'])) ?></div>
          </div>
        <?php endforeach; ?>
        <noscript><button class="btn btn-ghost" type="submit">Mettre à jour</button></noscript>
      </form>
      <?php foreach ($items as $item): ?>
        <form id="remove-<?= e($item['key']) ?>" action="<?= e(url('panier/supprimer')) ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($item['key']) ?>"></form>
      <?php endforeach; ?>

      <aside class="cart-summary">
        <h2>Récapitulatif</h2>
        <?php if ($remaining > 0): ?>
          <div class="free-shipping">
            <p>Plus que <strong><?= e(money($remaining)) ?></strong> pour la livraison offerte !</p>
            <div class="progress"><span style="width: <?= min(100, (int) round($subtotal * 100 / max(1, (int) setting('shipping_free_threshold')))) ?>%"></span></div>
          </div>
        <?php elseif ((int) setting('shipping_free_threshold') > 0): ?>
          <p class="free-shipping ok">🎉 Livraison standard offerte !</p>
        <?php endif; ?>
        <dl>
          <dt>Sous-total</dt><dd><?= e(money($subtotal)) ?></dd>
          <dt>Livraison</dt><dd><?= $shipping[0]['price'] === 0 ? 'Offerte' : 'dès ' . e(money($shipping[0]['price'])) ?></dd>
          <dt class="total">Total estimé</dt><dd class="total"><?= e(money($subtotal + $shipping[0]['price'])) ?></dd>
        </dl>
        <p class="muted small">Prix TTC. Code promo et choix de la livraison à l'étape suivante.</p>

        <form action="<?= e(url('commande')) ?>" method="post" class="checkout-form">
          <?= csrf_field() ?>
          <label class="checkbox"><input type="checkbox" name="cgv" value="1" required> <span>J'ai lu et j'accepte les <a href="<?= e(url('page/cgv')) ?>" target="_blank">conditions générales de vente</a>, et je reconnais que les articles personnalisés ne sont pas soumis au droit de rétractation.</span></label>
          <label class="checkbox"><input type="checkbox" name="newsletter" value="1"> <span>Je souhaite recevoir la newsletter (nouveautés, offres).</span></label>
          <button class="btn btn-primary btn-lg btn-block" type="submit" <?= $stripeReady ? '' : 'disabled' ?>>Commander et payer</button>
          <?php if (!$stripeReady): ?><p class="muted small">Paiement en ligne bientôt disponible.</p><?php endif; ?>
        </form>
        <p class="payment-note">🔒 Paiement sécurisé par Stripe · CB · Apple Pay · Google Pay</p>
      </aside>
    </div>
    <?php endif; ?>
  </div>
</section>
