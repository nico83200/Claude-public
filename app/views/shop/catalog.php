<?php
$giftMax = (int) setting('gift_max_price', 3000) / 100;
$query = array_filter([
    'q'               => $filters['q'],
    'tri'             => $filters['sort'] !== 'featured' ? $filters['sort'] : null,
    'personnalisable' => $filters['personalizable'] ? 1 : null,
    'prix_max'        => $filters['max_price'] ? $filters['max_price'] / 100 : null,
]);
$base = $category ? 'categorie/' . $category['slug'] : 'boutique';
$toggle = static fn (string $key, $value) => isset($query[$key]) ? array_diff_key($query, [$key => 1]) : $query + [$key => $value];
?>
<section class="page-hero">
  <div class="container">
    <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('/')) ?>">Accueil</a> / <a href="<?= e(url('boutique')) ?>">Boutique</a><?php if ($category): ?> / <span><?= e($category['name']) ?></span><?php endif; ?></nav>
    <?php if ($category): ?><p class="script-accent">collection</p><?php endif; ?>
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
        <a class="chip chip-rose <?= $filters['personalizable'] ? 'active' : '' ?>" href="<?= e(url($base, $toggle('personnalisable', 1))) ?>"><?= icon('sparkle', 14) ?> À personnaliser</a>
        <a class="chip chip-rose <?= $filters['max_price'] ? 'active' : '' ?>" href="<?= e(url($base, $toggle('prix_max', $giftMax))) ?>"><?= icon('gift', 14) ?> Moins de <?= (int) $giftMax ?> €</a>
      </div>
      <form method="get" class="sort-form">
        <?php foreach (array_diff_key($query, ['tri' => 1]) as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <label for="tri">Trier par</label>
        <select id="tri" name="tri" data-autosubmit>
          <?php foreach (['featured' => 'Nos coups de cœur', 'bestsellers' => 'Meilleures ventes', 'new' => 'Nouveautés', 'rating' => 'Mieux notés', 'price_asc' => 'Prix croissant', 'price_desc' => 'Prix décroissant'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= $filters['sort'] === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <p class="result-count"><?= $total ?> article<?= $total > 1 ? 's' : '' ?></p>

    <?php if ($products): ?>
      <div class="product-grid">
        <?php foreach ($products as $product) { partial('product-card', ['product' => $product]); } ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <p class="script-accent">Oups…</p>
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
