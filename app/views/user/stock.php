<div class="page-head">
  <div>
    <h1>Inventaire</h1>
    <p><?= e($center['name']) ?> · le stock augmente automatiquement à chaque réception ; corrigez-le ici par un comptage ou une sortie.<?= $lastCount ? ' Dernier comptage : ' . date_fr($lastCount, true) . '.' : '' ?></p>
  </div>
  <div class="row row-wrap">
    <a class="btn" href="<?= url('stock/history') ?>"><?= icon('clock', 18) ?> Mouvements</a>
    <button class="btn btn-primary" type="button" data-scan="stock"><?= icon('camera', 18) ?> Scanner un article</button>
  </div>
</div>

<div class="grid grid-4 mb-2">
  <div class="stat c-indigo"><div class="stat-icon g-indigo"><?= icon('layers', 24) ?></div><div><div class="stat-value"><?= count(stock_list((int)$center['id'])) ?></div><div class="stat-label">Articles suivis</div></div></div>
  <a class="stat c-pink" href="<?= url('stock', ['filter' => 'low']) ?>"><div class="stat-icon g-pink"><?= icon('alert', 24) ?></div><div><div class="stat-value"><?= $lowCount ?></div><div class="stat-label">Sous le seuil d'alerte</div></div></a>
  <a class="stat c-amber" href="<?= url('stock', ['filter' => 'empty']) ?>"><div class="stat-icon g-amber"><?= icon('minus-circle', 24) ?></div><div><div class="stat-value"><?= (int)val('SELECT COUNT(*) FROM stock WHERE center_id = ? AND qty = 0', [$center['id']]) ?></div><div class="stat-label">En rupture</div></div></a>
  <?php if (show_prices()): ?><div class="stat c-green"><div class="stat-icon g-green"><?= icon('euro', 24) ?></div><div><div class="stat-value"><?= money($value) ?></div><div class="stat-label">Valeur du stock<?= $filter ? ' (filtre)' : '' ?></div></div></div><?php endif; ?>
</div>

<div class="grid grid-2 mb-2">
    <form method="post" action="<?= url('stock/exit') ?>" class="card" id="exit-form">
      <?= csrf_field() ?>
      <div class="card-head"><h3><?= icon('minus-circle', 18) ?> Sortie / entrée rapide</h3></div>
      <div class="card-body">
        <div class="field"><label>Article</label>
          <div class="input-group">
            <select name="product_id" required id="exit-product">
              <option value="">Choisir un article suivi…</option>
              <?php foreach (stock_list((int)$center['id']) as $it): ?><option value="<?= (int)$it['product_id'] ?>"><?= e($it['name']) ?> (<?= (int)$it['qty'] ?>)</option><?php endforeach; ?>
            </select>
            <button class="btn" type="button" data-scan="fill-select:#exit-product" title="Scanner"><?= icon('barcode', 18) ?></button>
          </div>
        </div>
        <div class="form-grid">
          <div class="field"><label>Quantité</label><input type="number" name="qty" min="1" value="1" required></div>
          <div class="field"><label>Type</label><select name="mode"><option value="out">Sortie (consommation)</option><option value="in">Entrée manuelle</option></select></div>
        </div>
        <div class="field"><label>Motif</label><input type="text" name="note" placeholder="ex : salle 2, périmé, transfert…"></div>
        <button class="btn btn-amber" type="submit" style="width:100%"><?= icon('check', 16) ?> Enregistrer le mouvement</button>
      </div>
    </form>

    <form method="post" action="<?= url('stock/add') ?>" class="card">
      <?= csrf_field() ?>
      <div class="card-head"><h3><?= icon('plus', 18) ?> Suivre un nouvel article</h3></div>
      <div class="card-body">
        <div class="field"><label>Article du catalogue</label>
          <div class="input-group">
            <select name="product_id" required id="add-product">
              <option value="">Choisir…</option>
              <?php foreach ($catalog as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn" type="button" data-scan="fill-select:#add-product" title="Scanner"><?= icon('barcode', 18) ?></button>
          </div>
        </div>
        <div class="form-grid">
          <div class="field"><label>Stock actuel</label><input type="number" name="qty" min="0" value="0"></div>
          <div class="field"><label>Seuil d'alerte</label><input type="number" name="alert" min="0" value="0"></div>
        </div>
        <button class="btn" type="submit" style="width:100%"><?= icon('plus', 16) ?> Ajouter au suivi</button>
      </div>
    </form>
  </div>

<div>
  <form method="post" action="<?= url('stock/save') ?>" class="card" id="stock-form">
    <?= csrf_field() ?><input type="hidden" name="filter" value="<?= e($filter) ?>">
    <div class="card-head">
      <h2><?= icon('clipboard') ?> État du stock</h2>
      <div class="chips">
        <?php foreach (['' => 'Tous', 'low' => 'Stock bas', 'empty' => 'Rupture'] as $k => $l): ?><a class="chip <?= $filter === $k ? 'active' : '' ?>" href="<?= url('stock', ['filter' => $k ?: null]) ?>"><?= $l ?></a><?php endforeach; ?>
      </div>
    </div>
    <?php if ($items): ?>
    <div class="card-body" style="padding-bottom:0"><small class="muted"><?= icon('info', 14) ?> Pour un inventaire, saisissez la quantité réellement présente dans « Compté » (laissez vide les articles non comptés), puis enregistrez. L'écart est tracé dans l'historique.</small></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Article</th><th class="num">En stock</th><th class="num">En commande</th><th class="num">Compté</th><th class="num">Seuil d'alerte</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): $low = (int)$it['alert_qty'] > 0 && (int)$it['qty'] <= (int)$it['alert_qty']; ?>
        <tr class="<?= $low ? 'stock-low' : '' ?>" data-stock-row="<?= (int)$it['product_id'] ?>">
          <td style="min-width:280px"><div class="row"><?php partial('thumb', ['p' => $it, 'size' => 38]); ?><div>
            <a class="strong" href="<?= url('stock/history', ['product_id' => $it['product_id']]) ?>"><?= e($it['name']) ?></a>
            <div><small><?= e($it['unit']) ?> · <?= e($it['supplier_name']) ?><?= $it['counted_at'] ? ' · compté le ' . date_fr($it['counted_at']) : '' ?></small></div>
            <?php if ($it['barcode']): ?><div><small class="muted"><?= icon('barcode', 12) ?> <?= e($it['barcode']) ?></small></div><?php endif; ?>
          </div></div></td>
          <td class="num"><span class="stock-qty" style="color:<?= (int)$it['qty'] === 0 ? 'var(--red)' : ($low ? '#d97706' : 'inherit') ?>"><?= (int)$it['qty'] ?></span><?= $low ? '<div><span class="badge badge-red">Stock bas</span></div>' : '' ?></td>
          <td class="num"><?= (int)$it['on_order'] ? '<span class="badge badge-blue">+' . (int)$it['on_order'] . '</span>' : '<small class="muted">—</small>' ?></td>
          <td class="num"><input class="count-input" type="number" min="0" name="counted[<?= (int)$it['product_id'] ?>]" placeholder="—" inputmode="numeric" data-current="<?= (int)$it['qty'] ?>"></td>
          <td class="num"><input class="qty-input" type="number" min="0" name="alert[<?= (int)$it['product_id'] ?>]" value="<?= (int)$it['alert_qty'] ?>" style="width:70px!important"></td>
          <td class="nowrap text-right">
            <button class="btn btn-sm" type="button" data-exit="<?= (int)$it['product_id'] ?>" data-name="<?= e($it['name']) ?>" title="Déclarer une sortie"><?= icon('minus', 15) ?> Sortie</button>
            <?php if ($low): ?>
              <button class="btn btn-sm btn-primary" type="submit" form="reorder-<?= (int)$it['product_id'] ?>" title="Ajouter au panier"><?= icon('cart', 15) ?></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="card-foot row row-wrap">
      <input type="text" name="note" placeholder="Note d'inventaire (optionnel, ex : inventaire trimestriel)" style="flex:1;min-width:220px">
      <button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Enregistrer l'inventaire</button>
    </div>
    <?php else: ?>
      <div class="empty"><?= icon('layers') ?><h3><?= $filter ? 'Aucun article pour ce filtre' : 'Aucun article suivi pour l\'instant' ?></h3><p>Les articles réceptionnés sont ajoutés automatiquement. Vous pouvez aussi ajouter des articles au suivi ci-contre.</p></div>
    <?php endif; ?>
  </form>

</div>

<?php foreach ($items as $it): if ((int)$it['alert_qty'] > 0 && (int)$it['qty'] <= (int)$it['alert_qty']): ?>
  <form method="post" action="<?= url('cart/add') ?>" id="reorder-<?= (int)$it['product_id'] ?>" data-add-cart class="hidden"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$it['product_id'] ?>"><input type="hidden" name="qty" value="<?= max(1, (int)$it['alert_qty'] * 2 - (int)$it['qty']) ?>"></form>
<?php endif; endforeach; ?>
