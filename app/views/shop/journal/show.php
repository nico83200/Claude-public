<?php use App\Services\Journal; ?>
<article>
  <header class="page-hero journal-hero">
    <div class="container narrow">
      <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('/')) ?>">Accueil</a> / <a href="<?= e(url('journal')) ?>">Le journal</a><?php if ($post['tag']): ?> / <a href="<?= e(url('journal', ['theme' => $post['tag']])) ?>"><?= e($post['tag']) ?></a><?php endif; ?></nav>
      <?php if (!$post['published']): ?><p class="flash flash-info">Brouillon : visible seulement par l'administration.</p><?php endif; ?>
      <h1><?= e($post['title']) ?></h1>
      <p class="journal-meta"><?php if ($post['tag']): ?><span class="pill pill-blush"><?= e($post['tag']) ?></span><?php endif; ?> <?= e(date_fr($post['published_at'] ?: $post['created_at'], false)) ?> · <?= Journal::readingTime((string) $post['content']) ?> min de lecture</p>
      <?php if ($post['excerpt']): ?><p class="lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
    </div>
  </header>
  <?php if ($post['cover_image']): ?>
    <div class="container journal-cover"><img <?= img_attrs($post['cover_image'], [800, 1200], '(max-width: 960px) 100vw, 900px') ?> alt="" width="1200" height="800" fetchpriority="high"></div>
  <?php endif; ?>
  <div class="container narrow rich journal-content"><?= rich_text($post['content']) ?></div>
</article>

<?php if ($products): ?>
<section class="section section-blush">
  <div class="container">
    <div class="section-head"><div><p class="script-accent">cités dans l'article</p><h2>Pour aller <em>plus loin</em></h2></div></div>
    <div class="product-grid"><?php foreach ($products as $product) { partial('product-card', ['product' => $product]); } ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($others): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><div><p class="script-accent">à lire aussi</p><h2>Dans le <em>journal</em></h2></div><a class="link-arrow" href="<?= e(url('journal')) ?>">Tous les articles <?= icon('arrow', 16) ?></a></div>
    <div class="journal-grid journal-grid-3">
      <?php foreach ($others as $p): ?>
        <a class="journal-card" href="<?= e(url('journal/' . $p['slug'])) ?>">
          <div class="journal-media"><?php if ($p['cover_image']): ?><img <?= img_attrs($p['cover_image'], [400, 800], '(max-width: 960px) 100vw, 33vw') ?> alt="" loading="lazy" width="800" height="600"><?php endif; ?></div>
          <div class="journal-body"><span class="journal-meta"><?= e($p['tag']) ?></span><h3><?= e($p['title']) ?></h3></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
