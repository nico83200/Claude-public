<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('/')) ?>">Accueil</a> / <span>Le journal</span></nav>
    <p class="script-accent">le journal du club</p>
    <h1>Conseils, idées cadeaux <em>&amp; inspirations</em></h1>
    <p class="lead">Des articles écrits par des cavalières, pour les cavalières… et pour celles et ceux qui cherchent à leur faire plaisir.</p>
  </div>
</section>
<section class="section section-tight">
  <div class="container">
    <div class="toolbar">
      <div class="chips">
        <a class="chip <?= $tag === '' ? 'active' : '' ?>" href="<?= e(url('journal')) ?>">Tous les articles</a>
        <?php foreach (App\Services\Journal::TAGS as $t): ?>
          <a class="chip <?= $tag === $t ? 'active' : '' ?>" href="<?= e(url('journal', ['theme' => $t])) ?>"><?= e($t) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($posts): ?>
      <div class="journal-grid">
        <?php foreach ($posts as $i => $p): ?>
          <a class="journal-card <?= $i === 0 && $tag === '' ? 'is-featured' : '' ?>" href="<?= e(url('journal/' . $p['slug'])) ?>">
            <div class="journal-media"><?php if ($p['cover_image']): ?><img <?= img_attrs($p['cover_image'], [400, 800, 1200], $i === 0 ? '(max-width: 960px) 100vw, 60vw' : '(max-width: 960px) 100vw, 33vw') ?> alt="" loading="<?= $i < 2 ? 'eager' : 'lazy' ?>" width="800" height="600"><?php endif; ?></div>
            <div class="journal-body">
              <span class="journal-meta"><?php if ($p['tag']): ?><span class="pill pill-blush"><?= e($p['tag']) ?></span><?php endif; ?> <?= e(date_fr($p['published_at'], false)) ?> · <?= App\Services\Journal::readingTime((string) $p['content']) ?> min de lecture</span>
              <h2><?= e($p['title']) ?></h2>
              <p><?= e($p['excerpt']) ?></p>
              <span class="link-arrow">Lire l'article <?= icon('arrow', 16) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><p class="script-accent">bientôt de nouveaux articles</p><p>Revenez vite, ou rejoignez le club pour être prévenue.</p></div>
    <?php endif; ?>
  </div>
</section>
