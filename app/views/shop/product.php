<section class="section section-tight">
  <div class="container">
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
      <a href="<?= e(url('/')) ?>">Accueil</a> /
      <?php if ($product['category_slug']): ?><a href="<?= e(url('categorie/' . $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a> /<?php endif; ?>
      <span><?= e($product['name']) ?></span>
    </nav>
    <?php if ($landingUrl ?? null): ?>
      <p class="landing-link"><a href="<?= e($landingUrl) ?>"><?= icon('sparkle', 14) ?> Découvrir des exemples et comment ça marche</a></p>
    <?php endif; ?>

    <?php partial('product-main', compact('product', 'variants', 'personalization', 'reviewSummary', 'sizeGuide', 'delivery')); ?>
  </div>
</section>

<?php if (App\Services\Reviews::enabled() && $reviewSummary['count'] === 0): ?>
<section class="reviews-empty-band" id="avis">
  <div class="container"><p><?= icon('star', 16) ?> <strong>Pas encore d'avis sur cet article.</strong> <span class="muted">Après votre commande, vous serez invitée à partager le vôtre.</span></p></div>
</section>
<?php elseif (App\Services\Reviews::enabled()): ?>
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

