<section class="page-hero page-hero-sm">
  <div class="container">
    <p class="script-accent"><?= $shared ? 'des idées pour faire plaisir' : 'tes coups de cœur' ?></p>
    <h1><?= $shared ? ($from !== '' ? 'La liste d\'envies <em>de ' . e($from) . '</em>' : 'Une liste <em>d\'envies</em>') : 'Mes <em>favoris</em>' ?></h1>
    <?php if ($shared): ?><p class="lead">Ces articles lui feraient très plaisir ♡ Pense à l'option « C'est un cadeau » au moment de commander : ton message sera glissé dans le colis.</p><?php endif; ?>
  </div>
</section>
<section class="section section-tight">
  <div class="container" data-wishlist-page data-loaded="<?= $loaded ? '1' : '0' ?>">
    <?php if ($products): ?>
      <?php if (!$shared): ?>
        <div class="wishlist-share">
          <p class="muted">Un anniversaire, Noël qui approche ? Envoie ta liste à tes proches.</p>
          <div class="field-inline">
            <label class="sr-only" for="wish-name">Ton prénom</label>
            <input id="wish-name" type="text" placeholder="Ton prénom (facultatif)" maxlength="40" data-wish-name>
            <button type="button" class="btn btn-primary" data-wish-share><?= icon('gift', 16) ?> Partager ma liste</button>
          </div>
          <p class="small" data-wish-share-result hidden></p>
        </div>
      <?php endif; ?>
      <div class="product-grid">
        <?php foreach ($products as $product) { partial('product-card', ['product' => $product]); } ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <p class="script-accent"><?= $loaded ? 'aucun favori pour l\'instant' : 'chargement…' ?></p>
        <p>Touche le cœur ♡ d'un article pour le retrouver ici, et partage ta liste à l'approche d'un anniversaire.</p>
        <a class="btn btn-primary" href="<?= e(url('boutique')) ?>">Découvrir la boutique</a>
      </div>
    <?php endif; ?>
  </div>
</section>
