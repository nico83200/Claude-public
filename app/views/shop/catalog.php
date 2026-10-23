<?php
$query = array_filter(['q' => $filters['q'], 'tri' => $filters['sort'] !== 'featured' ? $filters['sort'] : null, 'personnalisable' => $filters['personalizable'] ? 1 : null]);
$base = $category ? 'categorie/' . $category['slug'] : 'boutique';
?>
<section class="page-head">
  <div class="container">
    <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('/')) ?>">Accueil</a> / <a href="<?= e(url('boutique')) ?>">Boutique</a><?php if ($category): ?> / <span><?= e($category['name']) ?></span><?php endif; ?></nav>
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($category && $category['description']): ?><p class="lead"><?= e($category['description']) ?></p><?php endif; ?>
  </div>
</section>

<section class="section section-tight">
  <div class="container">
    <div class="toolbar">
      <div class="chips">
        <a class="chip <?= !$category ? 'active' : '' ?>" href="<?= e(url('boutique', array_diff_key($query, ['q' => 1]))) ?>">Tout</a>
        <?php foreach ($categories as $c): ?>
          <a class="chip <?= $category && $category['id'] == $c['id'] ? 'active' : '' ?>" href="<?= e(url('categorie/' . $c['slug'], array_diff_key($query, ['q' => 1]))) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
        <a class="chip chip-gold <?= $filters['personalizable'] ? 'active' : '' ?>" href="<?= e(url($base, $filters['personalizable'] ? array_diff_key($query, ['personnalisable' => 1]) : $query + ['personnalisable' => 1])) ?>">✦ Personnalisables</a>
      </div>
      <form method="get" class="sort-form">
        <?php if ($filters['q']): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif; ?>
        <?php if ($filters['personalizable']): ?><input type="hidden" name="personnalisable" value="1"><?php endif; ?>
        <label for="tri">Trier</label>
        <select id="tri" name="tri" data-autosubmit>
          <?php foreach (['featured' => 'Sélection', 'new' => 'Nouveautés', 'price_asc' => 'Prix croissant', 'price_desc' => 'Prix décroissant', 'name' => 'Nom'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= $filters['sort'] === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <p class="muted"><?= $total ?> article<?= $total > 1 ? 's' : '' ?></p>

    <?php if ($products): ?>
      <div class="product-grid">
        <?php foreach ($products as $product) { partial('product-card', ['product' => $product]); } ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <p>Aucun article ne correspond à votre recherche.</p>
        <a class="btn btn-primary" href="<?= e(url('boutique')) ?>">Voir toute la boutique</a>
      </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
      <nav class="pagination" aria-label="Pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="<?= e(url($base, $query + ['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>
