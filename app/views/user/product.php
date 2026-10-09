<?php $price = effective_price($p); $hasDeal = $p['negotiated_price'] !== null && (float)$p['negotiated_price'] > 0 && (float)$p['negotiated_price'] < (float)$p['catalog_price']; ?>
<div class="breadcrumb"><a href="<?= url('catalog') ?>">Catalogue</a> <?= icon('chevron-right', 14) ?> <?php if ($p['category_name']): ?><a href="<?= url('catalog', ['cat' => $p['category_id']]) ?>"><?= e($p['category_name']) ?></a> <?= icon('chevron-right', 14) ?><?php endif; ?> <?= e($p['name']) ?></div>
<div class="grid grid-2 mt-1">
  <div class="card" style="overflow:hidden">
    <div class="product-img" style="aspect-ratio:1/1;<?= empty($p['image']) ? 'background:linear-gradient(135deg,' . e($p['category_color'] ?? '#8b5cf6') . ',' . e($p['supplier_color']) . ')' : '' ?>">
      <?php if ($p['image']): ?><img src="<?= e(product_image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>"><?php else: ?><div class="product-ph"><?= icon('box', 96) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="stack">
    <div>
      <?php if ($p['category_name']): ?><span class="badge badge-violet"><?= e($p['category_name']) ?></span><?php endif; ?>
      <?php if (!$p['active']): ?><span class="badge badge-gray">Article inactif</span><?php endif; ?>
      <h1 class="mt-1"><?= e($p['name']) ?></h1>
      <div class="muted"><span class="dot" style="background:<?= e($p['supplier_color']) ?>"></span> <?= e($p['supplier_name']) ?><?= $p['reference'] ? ' · Réf. ' . e($p['reference']) : '' ?><?= $p['unit'] ? ' · ' . e($p['unit']) : '' ?></div>
    </div>
    <?php if (show_prices()): ?>
    <div class="row">
      <div class="product-price" style="font-size:1.8rem"><?= money($price) ?> <small class="muted" style="font-size:.9rem;font-weight:500">HT</small></div>
      <?php if ($hasDeal && is_admin() && ($pc = product_contract((int)$p['id']))): ?><span class="badge badge-violet" title="Prix fixé jusqu'au <?= e(date_fr($pc['end_date'])) ?>">Prix <?= $pc['buying_group'] ? e($pc['buying_group']) : 'contrat' ?></span> <?php endif; ?><?php if ($hasDeal): ?><span class="badge badge-green">Tarif négocié · -<?= round(100 - $price / (float)$p['catalog_price'] * 100) ?> %</span> <s class="muted"><?= money($p['catalog_price']) ?></s><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($p['description']): ?><div class="card card-body"><?= nl2br(e($p['description'])) ?></div><?php endif; ?>
    <form method="post" action="<?= url('cart/add') ?>" class="card card-body" data-add-cart>
      <?= csrf_field() ?>
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <div class="row row-wrap">
        <label class="mb-0">Quantité</label>
        <input class="qty-input" type="number" name="qty" value="<?= max(1, (int)$p['min_qty']) ?>" min="1" max="9999">
        <button class="btn btn-primary" type="submit"><?= icon('cart', 18) ?> Ajouter au panier</button>
        <button class="btn <?= $isFav ? 'btn-danger' : '' ?>" type="button" data-fav="<?= (int)$p['id'] ?>"><?= icon('heart', 18) ?> <?= $isFav ? 'Retirer des favoris' : 'Favori' ?></button>
      </div>
      <?php if ($p['delivery_delay']): ?><small class="muted mt-1"><?= icon('truck', 14) ?> Délai fournisseur indicatif : <?= e($p['delivery_delay']) ?></small><?php endif; ?>
    </form>
    <?php if ($stock): ?>
    <div class="card stock-place">
      <div class="card-head"><h3><?= icon('map-pin', 18) ?> Dans votre centre</h3><a class="btn btn-sm btn-ghost" href="<?= url('stock/history', ['product_id' => $p['id']]) ?>"><?= icon('clock', 15) ?> Mouvements</a></div>
      <div class="card-body">
        <div class="stock-place-row">
          <div><small class="muted">Rangé à</small>
            <?php if ($stock['location']): ?><div class="stock-place-loc"><?= e($stock['location']) ?></div>
            <?php else: ?><div class="muted">Emplacement non renseigné</div><?php endif; ?></div>
          <div class="text-right"><small class="muted">En stock</small><div class="stock-place-qty" style="color:<?= (int)$stock['qty'] === 0 ? 'var(--red)' : ((int)$stock['alert_qty'] > 0 && (int)$stock['qty'] <= (int)$stock['alert_qty'] ? '#d97706' : 'inherit') ?>"><?= (int)$stock['qty'] ?></div></div>
        </div>
        <details class="mt-1" <?= $stock['location'] ? '' : 'open' ?>>
          <summary class="muted" style="cursor:pointer;font-size:.88rem"><?= icon('edit', 14) ?> <?= $stock['location'] ? 'Modifier l\'emplacement' : 'Indiquer où il est rangé' ?></summary>
          <form method="post" action="<?= url('stock/location') ?>" class="row row-wrap mt-1">
            <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="text" name="location" value="<?= e($stock['location']) ?>" maxlength="80" placeholder="ex : Réserve 1 · étagère B2 · bac 3" style="flex:1;min-width:200px">
            <button class="btn btn-sm btn-primary" type="submit"><?= icon('check', 15) ?> Enregistrer</button>
          </form>
        </details>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($history): ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('repeat', 18) ?> Dernières demandes dans ce centre</h3></div>
      <ul class="list"><?php foreach ($history as $h): ?><li><div class="grow"><?= e($h['first_name'] . ' ' . $h['last_name']) ?></div><span class="badge badge-gray">× <?= (int)$h['qty'] ?></span><small><?= date_fr($h['created_at']) ?></small></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <div class="row row-wrap">
      <a class="btn btn-sm" href="<?= url('labels', ['ids' => $p['id']]) ?>" target="_blank" rel="noopener" title="Étiquette d'étagère avec code-barres scannable"><?= icon('printer', 16) ?> Imprimer l'étiquette</a>
      <?php if (is_admin()): ?><a class="btn btn-sm" href="<?= url('admin/product', ['id' => $p['id']]) ?>"><?= icon('edit', 16) ?> Modifier l'article</a><?php endif; ?>
    </div>
  </div>
</div>
<?php if ($similar): ?>
<h2 class="mt-3">Articles similaires</h2>
<div class="products"><?php foreach ($similar as $s) { partial('product_card', ['p' => $s, 'favIds' => user_favorites((int)user()['id']), 'deadlinesBySupplier' => []]); } ?></div>
<?php endif; ?>
