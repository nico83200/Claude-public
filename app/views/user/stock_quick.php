<div class="quick" id="quick" data-center="<?= (int)$center['id'] ?>">
  <header class="quick-head">
    <a href="<?= url('stock') ?>" class="quick-back"><?= icon('arrow-left', 22) ?></a>
    <div><div class="quick-title">Inventaire tablette</div><div class="quick-sub"><?= e($center['name']) ?> · scannez, saisissez le stock présent, validez</div></div>
  </header>
  <button type="button" class="quick-scan" data-quick-scan><?= icon('camera', 44) ?><span>Scanner un article</span></button>
  <form class="quick-search" data-quick-search><input type="search" placeholder="… ou saisir un code-barres / une référence (douchette)" inputmode="search"><button class="btn" type="submit"><?= icon('search', 20) ?></button></form>
  <div class="quick-card hidden" data-quick-card>
    <div class="quick-product"><div class="quick-img" data-q-img></div><div><div class="quick-name" data-q-name></div><div class="quick-meta" data-q-meta></div></div></div>
    <div class="quick-stock">Stock théorique : <strong data-q-stock>—</strong></div>
    <div class="quick-loc" data-q-loc hidden></div>
    <label class="quick-label" for="q-qty">Stock actuel (quantité présente)</label>
    <div class="quick-qty"><button type="button" data-step="-1" aria-label="Moins un">−</button><input id="q-qty" type="number" min="0" value="0" data-q-qty inputmode="numeric"><button type="button" data-step="1" aria-label="Plus un">+</button></div>
    <div class="quick-diff" data-q-diff></div>
    <button type="button" class="quick-go quick-count" data-q-go><?= icon('check', 22) ?> Valider le stock</button>
    <small class="quick-hint">L'entrée ou la sortie est calculée automatiquement. Après validation, le scanner se relance pour l'article suivant.</small>
  </div>
  <ul class="quick-log" data-quick-log></ul>
</div>
