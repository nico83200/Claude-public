<?php $q = ['c' => $center['id']] + ($product ? ['product_id' => $product['id']] : []); ?>
<div class="breadcrumb"><a href="<?= url('stock') ?>">Inventaire</a> <?= icon('chevron-right', 14) ?> Mouvements</div>
<div class="page-head">
  <div>
    <h1><?= $product ? e($product['name']) : 'Mouvements de stock' ?></h1>
    <p><?= e($center['name']) ?><?php if ($product): ?> · en stock : <strong><?= (int)$product['qty'] ?></strong><?= (int)$product['alert_qty'] ? ' · seuil ' . (int)$product['alert_qty'] : '' ?><?php endif; ?></p>
  </div>
  <?php if ($product): ?>
  <form method="post" action="<?= url('stock/remove', ['product_id' => $product['id']]) ?>" onsubmit="return confirm('Ne plus suivre cet article dans ce centre ?')"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 15) ?> Ne plus suivre</button></form>
  <?php endif; ?>
</div>
<?php if ($isAdmin && $product && $product['qty'] !== null && count($centers) > 1): ?>
<form method="post" action="<?= url('stock/transfer') ?>" class="card mb-2">
  <?= csrf_field() ?><input type="hidden" name="from" value="<?= (int)$center['id'] ?>"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
  <div class="card-body row row-wrap" style="align-items:flex-end;gap:.8rem">
    <div class="field mb-0" style="flex:1;min-width:220px"><label><?= icon('building', 16) ?> Stock saisi dans le mauvais centre ? Le rattacher à :</label>
      <select name="to" required><option value="">Choisir le centre…</option>
        <?php foreach ($centers as $c): if ((int)$c['id'] === (int)$center['id']) continue; ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <button class="btn" type="submit" data-confirm="Rattacher le stock de cet article (<?= (int)$product['qty'] ?>) et son historique au centre choisi ? Si l'article y est déjà suivi, les quantités seront additionnées."><?= icon('truck', 16) ?> Changer de centre</button>
  </div>
</form>
<?php endif; ?>
<div class="card">
  <?php if ($moves): ?>
  <?php if ($isAdmin): ?>
  <form method="post" action="<?= url('stock/move/delete', $q) ?>" id="moves-bulk">
    <?= csrf_field() ?>
    <div class="bulk-bar" hidden>
      <span><strong data-bulk-count>0</strong> mouvement(s) sélectionné(s)</span>
      <button class="btn btn-sm btn-danger" type="submit" data-confirm="Supprimer les mouvements sélectionnés ? Leur effet sur le stock sera annulé."><?= icon('trash', 16) ?> Supprimer la sélection</button>
    </div>
  </form>
  <?php endif; ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><?php if ($isAdmin): ?><th class="col-check"><input type="checkbox" form="moves-bulk" data-check-all="ids[]" aria-label="Tout sélectionner"></th><?php endif; ?><th>Date</th><?php if (!$product): ?><th>Article</th><?php endif; ?><th>Type</th><th class="num">Variation</th><th class="num">Stock après</th><th>Par</th><th>Motif</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($moves as $m): $t = STOCK_MOVE_TYPES[$m['type']] ?? ['label' => $m['type'], 'color' => 'gray']; ?>
      <tr>
        <?php if ($isAdmin): ?><td class="col-check"><input type="checkbox" form="moves-bulk" name="ids[]" value="<?= (int)$m['id'] ?>" aria-label="Sélectionner ce mouvement"></td><?php endif; ?>
        <td class="nowrap"><?= date_fr($m['created_at'], true) ?></td>
        <?php if (!$product): ?><td><a href="<?= url('stock/history', ['product_id' => $m['product_id']]) ?>"><?= e($m['name']) ?></a></td><?php endif; ?>
        <td><?= badge($t) ?></td>
        <td class="num strong" style="color:<?= $m['delta'] > 0 ? 'var(--green)' : ($m['delta'] < 0 ? 'var(--red)' : 'var(--muted)') ?>"><?= $m['delta'] > 0 ? '+' : '' ?><?= (int)$m['delta'] ?></td>
        <td class="num"><?= (int)$m['qty_after'] ?></td>
        <td><small><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?: '—' ?></small></td>
        <td><small><?= e($m['note']) ?></small></td>
        <?php if ($isAdmin): ?>
        <td class="nowrap actions">
          <a class="btn btn-sm btn-ghost" href="<?= url('stock/move/edit', ['id' => $m['id']]) ?>" title="Corriger (quantité, centre, motif)"><?= icon('edit', 15) ?></a>
          <form method="post" action="<?= url('stock/move/delete', $q + ['id' => $m['id']]) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit" title="Supprimer" data-confirm="Supprimer ce mouvement (<?= e($t['label']) ?> <?= (int)$m['delta'] > 0 ? '+' : '' ?><?= (int)$m['delta'] ?>) ? Son effet sur le stock sera annulé."><?= icon('trash', 15) ?></button></form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><div class="empty"><?= icon('clock') ?><p>Aucun mouvement enregistré.</p></div><?php endif; ?>
</div>
