<div class="breadcrumb"><a href="<?= url('kits') ?>">Listes types</a> <?= icon('chevron-right', 14) ?> <?= e($kit['name'] ?? 'Nouvelle liste') ?></div>
<form method="post" action="<?= url('kit/save', array_filter(['id' => $kit['id'] ?? null])) ?>" class="grid grid-main mt-1">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2><?= icon('clipboard') ?> <?= $kit ? e($kit['name']) : 'Nouvelle liste type' ?></h2>
      <?php if ($kit && $items): ?><button class="btn btn-primary btn-sm" type="submit" formaction="<?= url('kit/to-cart', ['id' => $kit['id']]) ?>"><?= icon('cart', 15) ?> Ajouter au panier</button><?php endif; ?></div>
    <?php if ($items): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Article</th><th>Fournisseur</th><th class="num">Quantité</th><?php if (show_prices()): ?><th class="num">Total</th><?php endif; ?></tr></thead>
      <tbody><?php $tot = 0; foreach ($items as $it): $tot += effective_price($it) * $it['qty']; ?>
        <tr>
          <td><div class="row"><?php partial('thumb', ['p' => $it, 'size' => 34]); ?><div><a class="strong" href="<?= url('product', ['id' => $it['id']]) ?>"><?= e($it['name']) ?></a><div><small><?= e($it['unit']) ?></small></div></div></div></td>
          <td><small><?= e($it['supplier_name']) ?></small></td>
          <td class="num"><input class="qty-input" type="number" min="0" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>" <?= $canEdit ? '' : '' ?>></td>
          <?php if (show_prices()): ?><td class="num"><?= money(effective_price($it) * $it['qty']) ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?></tbody>
      <?php if (show_prices()): ?><tfoot><tr><td colspan="3" class="text-right">Total estimé HT</td><td class="num"><?= money($tot) ?></td></tr></tfoot><?php endif; ?>
    </table></div>
    <?php else: ?><div class="empty"><?= icon('box') ?><p>Ajoutez des articles avec le formulaire ci-contre.</p></div><?php endif; ?>
  </div>
  <div class="stack">
    <?php if ($canEdit): ?>
    <div class="card card-body">
      <div class="field"><label>Nom de la liste *</label><input type="text" name="name" value="<?= e($kit['name'] ?? '') ?>" required placeholder="ex : Kit consultation, Commande mensuelle accueil"></div>
      <div class="field"><label>Description</label><input type="text" name="description" value="<?= e($kit['description'] ?? '') ?>"></div>
      <?php if (is_admin()): ?>
        <label class="check"><input type="checkbox" name="shared" value="1" <?= ($kit['shared'] ?? 1) ? 'checked' : '' ?>> Liste partagée avec les salariés</label>
        <div class="field"><label>Centre concerné</label><select name="center_id"><option value="">Tous les centres</option><?php foreach ($centers as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($kit['center_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <hr>
      <label>Ajouter un article</label>
      <select name="add_product" class="mb-1"><option value="">Choisir…</option><?php foreach ($catalog as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
      <div class="row"><input class="qty-input" type="number" name="add_qty" min="1" value="1"><small class="muted">quantité</small></div>
      <button class="btn btn-primary mt-2" type="submit"><?= icon('check', 16) ?> Enregistrer</button>
      <small class="muted mt-1">Mettez une quantité à 0 pour retirer un article.</small>
    </div>
    <?php if ($kit): ?>
      <button class="btn btn-danger btn-sm" type="submit" formaction="<?= url('kit/delete', ['id' => $kit['id']]) ?>" formnovalidate onclick="return confirm('Supprimer cette liste ?')"><?= icon('trash', 15) ?> Supprimer la liste</button>
    <?php endif; ?>
    <?php else: ?>
      <div class="card card-body"><p class="muted mb-0">Liste partagée par le service achats : vous pouvez ajuster les quantités puis l'ajouter à votre panier.</p></div>
    <?php endif; ?>
  </div>
</form>
