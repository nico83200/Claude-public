<section class="hero">
  <div class="container hero-inner">
    <div class="hero-text">
      <p class="eyebrow">Collection écurie <?= date('Y') ?></p>
      <h1><?= e(setting('hero_title')) ?></h1>
      <p class="hero-sub"><?= e(setting('hero_subtitle')) ?></p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="<?= e(url('boutique')) ?>">Découvrir la boutique</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('boutique', ['personnalisable' => 1])) ?>">Personnaliser un article</a>
      </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <img src="<?= e(url('assets/img/hero.svg')) ?>" alt="" width="560" height="560">
    </div>
  </div>
</section>

<?php if ($categories): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Nos univers</h2>
      <a href="<?= e(url('boutique')) ?>">Tout voir →</a>
    </div>
    <div class="category-grid">
      <?php foreach ($categories as $i => $c): ?>
        <a class="category-tile tile-<?= $i % 4 ?>" href="<?= e(url('categorie/' . $c['slug'])) ?>">
          <span class="category-name"><?= e($c['name']) ?></span>
          <span class="category-count"><?= (int) $c['product_count'] ?> article<?= $c['product_count'] > 1 ? 's' : '' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Les coups de cœur du paddock</h2>
      <a href="<?= e(url('boutique')) ?>">Toute la collection →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $product) { partial('product-card', ['product' => $product]); } ?>
    </div>
  </div>
</section>

<section class="section section-band">
  <div class="container split">
    <div>
      <p class="eyebrow">Unique comme votre cheval</p>
      <h2>Son nom, brodé sur votre cœur</h2>
      <p>Tapez le nom de votre cheval, de votre poney ou de votre écurie : l'aperçu s'affiche en direct, et notre atelier l'imprime rien que pour vous. Le cadeau parfait pour une propriétaire, une demi-pension ou toute l'équipe de concours.</p>
      <a class="btn btn-primary" href="<?= e(url('boutique', ['personnalisable' => 1])) ?>">Voir les articles personnalisables</a>
    </div>
    <ol class="steps">
      <li><strong>Choisissez</strong> votre article, sa couleur et sa taille.</li>
      <li><strong>Personnalisez</strong> avec le nom de votre cheval — aperçu instantané.</li>
      <li><strong>Recevez</strong> votre pièce unique, imprimée en Europe sous 2 à 5 jours.</li>
    </ol>
  </div>
</section>

<?php if ($personalized): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><h2>À personnaliser</h2></div>
    <div class="product-grid">
      <?php foreach ($personalized as $product) { partial('product-card', ['product' => $product]); } ?>
    </div>
  </div>
</section>
<?php endif; ?>
