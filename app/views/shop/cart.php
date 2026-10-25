<section class="page-hero page-hero-sm">
  <div class="container"><h1>Mon <em>panier</em></h1></div>
</section>

<section class="section section-tight">
  <div class="container">
    <?php if (isset($_GET['annule'])): ?>
      <div class="flash flash-info">Paiement annulé : votre panier vous attend toujours ♡</div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="empty">
        <p class="script-accent">votre panier est vide</p>
        <p>Laissez-vous tenter par nos trois collections.</p>
        <a class="btn btn-primary" href="<?= e(url('boutique')) ?>">Découvrir la boutique</a>
      </div>
    <?php else: ?>
    <?php
    $hasCustom = (bool) array_filter($items, static fn ($i) => $i['mode'] || $i['text'] !== '' || $i['photo'] || $i['portrait']);
    $total = $subtotal + $shipping[0]['price'];
    ?>
    <div class="cart-layout">
      <div class="cart-main">
        <form action="<?= e(url('panier/modifier')) ?>" method="post" id="cart-form"><?= csrf_field() ?><noscript><button class="btn btn-ghost btn-sm" type="submit">Mettre à jour les quantités</button></noscript></form>
        <div class="cart-items">
          <?php foreach ($items as $item) { partial('line-item', ['item' => $item]); } ?>
        </div>
      </div>
      <?php if ($boosters): ?>
        <div class="boosters cart-extras">
          <h3><?= icon('truck', 18) ?> Plus que <?= e(money($remaining)) ?> pour la livraison offerte — complétez avec :</h3>
          <div class="booster-list"><?php foreach ($boosters as $b) { partial('booster', ['product' => $b]); } ?></div>
        </div>
      <?php endif; ?>

      <aside class="summary-card" id="paiement">
        <h2>Récapitulatif</h2>
        <?php partial('shipping-progress', ['remaining' => $remaining, 'progress' => $progress]); ?>
        <dl>
          <dt>Sous-total</dt><dd><?= e(money($subtotal)) ?></dd>
          <dt>Livraison</dt><dd><?= $shipping[0]['price'] === 0 ? 'Offerte' : 'dès ' . e(money($shipping[0]['price'])) ?></dd>
          <dt class="total">Total estimé</dt><dd class="total"><?= e(money($total)) ?></dd>
        </dl>
        <p class="muted small">Prix TTC. Code promo et choix de la livraison à l'étape suivante.</p>

        <form action="<?= e(url('commande')) ?>" method="post" class="checkout-form">
          <?= csrf_field() ?>
          <div class="gift-option">
            <label class="checkbox"><input type="checkbox" name="gift" value="1" data-gift-toggle> <span><?= icon('gift', 16) ?> <strong>C'est un cadeau</strong> : j'ajoute un petit mot</span></label>
            <div class="gift-fields" data-gift-fields hidden>
              <div class="field"><label for="gift-to">Pour</label><input id="gift-to" name="gift_to" maxlength="60" placeholder="Prénom de la personne"></div>
              <div class="field"><label for="gift-message">Votre message <small class="muted" data-gift-count>0 / 200</small></label><textarea id="gift-message" name="gift_message" rows="3" maxlength="200" placeholder="Joyeux anniversaire ! Bisous à Tornado ♡"></textarea></div>
              <p class="muted small">Imprimé sur le bon de livraison glissé dans le colis. Vous saisirez l'adresse de la personne à l'étape suivante.</p>
            </div>
          </div>
          <label class="checkbox"><input type="checkbox" name="cgv" value="1" required data-cgv> <span>J'accepte les <a href="<?= e(url('page/cgv')) ?>" target="_blank">conditions générales de vente</a><?= $hasCustom ? ' et je sais que les articles personnalisés, fabriqués rien que pour moi, ne sont pas soumis au droit de rétractation' : '' ?>.</span></label>
          <button class="btn btn-primary btn-lg btn-block" type="submit" <?= $stripeReady ? '' : 'disabled' ?>><?= icon('lock', 18) ?> Paiement sécurisé</button>
          <details class="checkout-more">
            <summary>Options : newsletter, compte cliente</summary>
            <label class="checkbox"><input type="checkbox" name="newsletter" value="1"> <span>Je rejoins le club : nouveautés, offres, et un petit rappel si je n'ai pas terminé ma commande.</span></label>
          </details>
          <?php $shopper = App\Services\Account::current(); ?>
          <p class="checkout-account muted small">
            <?php if ($shopper): ?>
              <?= icon('user', 15) ?> Commande rattachée à votre compte <strong><?= e($shopper['email']) ?></strong>.
            <?php else: ?>
              <?= icon('user', 15) ?> Vous pourrez créer votre compte en un clic après le paiement. Déjà cliente ? <a href="<?= e(url('compte/connexion', ['retour' => 'panier'])) ?>">Connectez-vous</a>
            <?php endif; ?>
          </p>
          <?php if (!$stripeReady): ?><p class="muted small center">Paiement en ligne bientôt disponible.</p><?php endif; ?>
        </form>
        <p class="pay-methods center-flex" aria-label="Moyens de paiement"><span>CB</span><span>VISA</span><span>Mastercard</span><span>Apple Pay</span><span>Google Pay</span></p>
        <ul class="summary-perks">
          <li><?= icon('leaf', 16) ?> Imprimé rien que pour vous en Europe</li>
          <li><?= icon('refresh', 16) ?> Un défaut ? Nous réimprimons gratuitement</li>
          <li><?= icon('heart', 16) ?> Une question ? <a href="<?= e(url('contact')) ?>">Écrivez-nous</a></li>
        </ul>
      </aside>
    </div>

    <div class="cart-paybar" data-cart-paybar hidden>
      <div><small>Total estimé</small><strong><?= e(money($total)) ?></strong></div>
      <a class="btn btn-primary" href="#paiement" data-paybar-go><?= icon('lock', 16) ?> Commander</a>
    </div>
    <?php endif; ?>
  </div>
</section>
