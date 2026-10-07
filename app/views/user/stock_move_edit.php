<?php
$t = STOCK_MOVE_TYPES[$m['type']] ?? ['label' => $m['type'], 'color' => 'gray'];
$isCount = $m['type'] === 'inventaire';
$qty = $isCount ? (int)$m['qty_after'] : abs((int)$m['delta']);
$back = url('stock/history', ['c' => $m['center_id'], 'product_id' => $m['product_id']]);
?>
<div class="breadcrumb"><a href="<?= url('stock', ['c' => $m['center_id']]) ?>">Inventaire</a> <?= icon('chevron-right', 14) ?> <a href="<?= $back ?>"><?= e($m['name']) ?></a> <?= icon('chevron-right', 14) ?> Corriger</div>
<div class="page-head"><div><h1>Corriger un mouvement</h1><p><?= e($m['name']) ?> · <?= badge($t) ?> du <?= date_fr($m['created_at'], true) ?><?= $m['first_name'] ? ' par ' . e($m['first_name'] . ' ' . $m['last_name']) : '' ?></p></div></div>
<form method="post" class="card" style="max-width:640px">
  <?= csrf_field() ?>
  <div class="card-body">
    <?php if ($m['po_number']): ?><div class="flash flash-info mb-1"><?= icon('info', 18) ?> Mouvement issu de la réception du bon <?= e($m['po_number']) ?> : le corriger ne modifie pas les quantités reçues du bon de commande.</div><?php endif; ?>
    <div class="field"><label>Centre</label>
      <select name="center_id">
        <?php foreach ($centers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$m['center_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <small>En cas d'erreur de centre : le mouvement est retiré du stock de <?= e($m['center_name']) ?> et appliqué au centre choisi, à la même date.</small></div>
    <div class="field"><label><?= $isCount ? 'Quantité comptée' : ($m['delta'] < 0 ? 'Quantité sortie' : 'Quantité entrée') ?> <?= $m['unit'] ? '(' . e($m['unit']) . ')' : '' ?></label>
      <input type="number" name="qty" min="<?= $isCount ? 0 : 1 ?>" step="1" value="<?= $qty ?>" required inputmode="numeric"></div>
    <div class="field mb-0"><label>Motif</label><input type="text" name="note" maxlength="255" value="<?= e($m['note']) ?>"></div>
  </div>
  <div class="card-foot row row-wrap" style="justify-content:space-between">
    <a class="btn btn-ghost" href="<?= $back ?>">Annuler</a>
    <button class="btn btn-primary" type="submit"><?= icon('check', 16) ?> Enregistrer la correction</button>
  </div>
</form>
<p class="muted mt-1" style="font-size:.85rem;max-width:640px">Le stock et les « stock après » des mouvements suivants sont recalculés automatiquement. Un inventaire postérieur reste la référence : la quantité comptée ne change pas, seul son écart est ajusté.</p>
