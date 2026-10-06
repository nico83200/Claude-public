<div class="quick" id="quick" data-center="<?= (int)$center['id'] ?>">
  <header class="quick-head">
    <a href="<?= url('stock') ?>" class="quick-back"><?= icon('arrow-left', 22) ?></a>
    <div><div class="quick-title">Mode réserve</div><div class="quick-sub"><?= e($center['name']) ?></div></div>
  </header>
  <div class="quick-mode" role="tablist">
    <button type="button" class="active" data-mode="out"><?= icon('minus-circle', 20) ?> Sortie</button>
    <button type="button" data-mode="in"><?= icon('plus', 20) ?> Entrée</button>
    <button type="button" data-mode="count"><?= icon('clipboard', 20) ?> Inventaire</button>
  </div>
  <button type="button" class="quick-scan" data-quick-scan><?= icon('camera', 44) ?><span>Scanner un article</span></button>
  <form class="quick-search" data-quick-search><input type="search" placeholder="… ou saisir un code-barres / une référence" inputmode="search"><button class="btn" type="submit"><?= icon('search', 20) ?></button></form>
  <div class="quick-card hidden" data-quick-card>
    <div class="quick-product"><div class="quick-img" data-q-img></div><div><div class="quick-name" data-q-name></div><div class="quick-meta" data-q-meta></div></div></div>
    <div class="quick-stock">En stock : <strong data-q-stock>—</strong></div>
    <div class="quick-qty"><button type="button" data-step="-1">−</button><input type="number" min="0" value="1" data-q-qty inputmode="numeric"><button type="button" data-step="1">+</button></div>
    <input type="text" placeholder="Motif (optionnel)" data-q-note class="quick-note">
    <button type="button" class="quick-go" data-q-go>Valider la sortie</button>
  </div>
  <ul class="quick-log" data-quick-log></ul>
</div>
