<div class="page-head">
  <div>
    <h1>Catalogue</h1>
    <p><?= plural(count($products), 'article', 'articles') ?><?= $query !== '' ? ' pour « ' . e($query) . ' »' : ' disponibles pour ' . e($center['name']) ?></p>
  </div>
  <a class="btn" href="<?= url('cart') ?>"><?= icon('cart', 18) ?> Voir mon panier</a>
</div>

<form class="search-box mb-2" method="get" action="index.php" data-suggest>
  <input type="hidden" name="r" value="catalog">
  <?php if ($category): ?><input type="hidden" name="cat" value="<?= $category ?>"><?php endif; ?>
  <?php if ($supplier): ?><input type="hidden" name="sup" value="<?= $supplier ?>"><?php endif; ?>
  <span class="ic-left"><?= icon('search', 20) ?></span>
  <input type="search" name="q" value="<?= e($query) ?>" placeholder="Décrivez votre besoin : « de quoi nettoyer les tables d'examen », « gants M »…" autocomplete="off" autofocus>
  <button class="btn btn-primary" type="submit"><?= icon('sparkles', 16) ?> Rechercher</button>
  <button class="btn btn-ghost btn-icon" type="button" data-scan="search" title="Scanner un code-barres" style="position:absolute;right:140px;top:50%;transform:translateY(-50%)"><?= icon('barcode', 20) ?></button>
</form>

<div class="chips mb-1">
  <a class="chip <?= !$category && !$favOnly ? 'active' : '' ?>" href="<?= url('catalog', array_filter(['q' => $query, 'sup' => $supplier])) ?>">Tout</a>
  <a class="chip <?= $favOnly ? 'active' : '' ?>" href="<?= url('catalog', ['fav' => 1]) ?>"><?= icon('heart', 15) ?> Mes favoris</a>
  <?php foreach ($categories as $c): if (!$c['nb']) continue; ?>
    <a class="chip <?= $category === (int)$c['id'] ? 'active' : '' ?>" href="<?= url('catalog', array_filter(['cat' => $c['id'], 'q' => $query, 'sup' => $supplier])) ?>">
      <span class="dot" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?> <small><?= (int)$c['nb'] ?></small>
    </a>
  <?php endforeach; ?>
</div>
<?php if (count($suppliers) > 1): ?>
<div class="chips mb-2">
  <?php foreach ($suppliers as $s): ?>
    <a class="chip <?= $supplier === (int)$s['id'] ? 'active' : '' ?>" style="font-size:.78rem" href="<?= url('catalog', array_filter(['sup' => $supplier === (int)$s['id'] ? null : $s['id'], 'q' => $query, 'cat' => $category])) ?>">
      <?= icon('truck', 14) ?> <?= e($s['name']) ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($query !== '' && $aiEnabled): ?>
<div class="ai-panel" id="ai-panel" data-query="<?= e($query) ?>">
  <div class="ai-head"><span class="ai-badge"><?= icon('sparkles', 18) ?></span> Assistant IA <small class="muted" style="font-weight:500">— suggestions selon votre besoin</small></div>
  <div class="ai-body">
    <div class="ai-loading mt-1"><span class="spinner"></span> L'assistant analyse votre demande…</div>
  </div>
</div>
<?php endif; ?>

<?php if ($products): ?>
  <?php if ($query !== '' && $aiEnabled): ?><h3 class="muted" style="font-weight:600">Résultats de la recherche</h3><?php endif; ?>
  <div class="products">
    <?php foreach ($products as $p) { partial('product_card', ['p' => $p, 'favIds' => $favIds, 'deadlinesBySupplier' => $deadlinesBySupplier]); } ?>
  </div>
<?php else: ?>
  <div class="card"><div class="empty">
    <?= icon('search') ?>
    <h3>Aucun article trouvé</h3>
    <p>Essayez avec d'autres mots, ou décrivez simplement l'usage (« pour nettoyer… », « pour le bureau… »).<br>
    L'article n'existe pas au catalogue ? Proposez-le au service achats :</p>
    <a class="btn btn-primary" href="<?= url('suggest', ['from' => 'cart', 'name' => $query]) ?>"><?= icon('sparkles', 18) ?> Proposer « <?= e(mb_substr($query, 0, 40)) ?> »</a>
  </div></div>
<?php endif; ?>
<?php if ($products && $query !== ''): ?>
  <p class="text-center muted mt-3">Vous ne trouvez pas votre article ? <a href="<?= url('suggest', ['from' => 'cart', 'name' => $query]) ?>">Proposez un article hors catalogue</a>.</p>
<?php endif; ?>
