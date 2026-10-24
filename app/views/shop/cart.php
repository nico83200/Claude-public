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
    <div class="cart-layout">
      <div>
        <form action="<?= e(url('panier/modifier')) ?>" method="post" id="cart-form"><?= csrf_field() ?><noscript><button class="btn btn-ghost btn-sm" type="submit">Mettre à jour les quantités</button></noscript></form>
        <div class="cart-items">
          <?php foreach ($items as $item) { partial('line-item', ['item' => $item]); } ?>
        </div>
        <?php if ($boosters): ?>
          <div class="boosters">
            <h3><?= icon('truck', 18) ?> Plus que <?= e(money($remaining)) ?> pour la livraison offerte — complétez avec :</h3>
            <div class="booster-list"><?php foreach ($boosters as $b) { partial('booster', ['product' => $b]); } ?></div>
          </div>
        <?php endif; ?>
      </div>

      <aside class="summary-card" id="paiement">
        <h2>Récapitulatif</h2>
        <?php partial('shipping-progress', ['remaining' => $remaining, 'progress' => $progress]); ?>
        <dl>
          <dt>Sous-total</dt><dd><?= e(money($subtotal)) ?></dd>
          <dt>Livraison</dt><dd><?= $shipping[0]['price'] === 0 ? 'Offerte' : 'dès ' . e(money($shipping[0]['price'])) ?></dd>
          <dt class="total">Total estimé</dt><dd class="total"><?= e(money($subtotal + $shipping[0]['price'])) ?></dd>
        </dl>
        <p class="muted small">Prix TTC. Code promo et choix de la livraison à l'étape suivante.</p>

        <form action="<?= e(url('commande')) ?>" method="post" class="checkout-form">
          <?= csrf_field() ?>
          <label class="checkbox"><input type="checkbox" name="cgv" value="1" required> <span>J'accepte les <a href="<?= e(url('page/cgv')) ?>" target="_blank">conditions générales de vente</a> et je reconnais que les articles personnalisés ne sont pas soumis au droit de rétractation.</span></label>
          <label class="checkbox"><input type="checkbox" name="newsletter" value="1"> <span>Je rejoins le club : nouveautés et offres par e-mail.</span></label>
          <?php $shopper = App\Services\Account::current(); ?>
          <div class="checkout-account">
            <?php if ($shopper): ?>
              <p><?= icon('user', 16) ?> Commande rattachée à votre compte <strong><?= e($shopper['email']) ?></strong>.</p>
            <?php else: ?>
              <label class="checkbox"><input type="checkbox" name="create_account" value="1" checked> <span><strong>Créer mon compte client</strong> après le paiement : suivi de commande, historique et commandes plus rapides.</span></label>
              <p class="muted">Déjà cliente ? <a href="<?= e(url('compte/connexion', ['retour' => 'panier'])) ?>">Connectez-vous</a></p>
            <?php endif; ?>
          </div>
          <button class="btn btn-primary btn-lg btn-block" type="submit" <?= $stripeReady ? '' : 'disabled' ?>><?= icon('lock', 18) ?> Paiement sécurisé</button>
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
    <?php endif; ?>
  </div>
</section>
