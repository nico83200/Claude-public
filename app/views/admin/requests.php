<div class="page-head">
  <div><h1>Demandes à traiter</h1><p>Les demandes sont classées par fournisseur puis par centre. Sélectionnez les lignes et créez le bon de commande.</p></div>
  <form method="get" class="row">
    <input type="hidden" name="r" value="admin/requests">
    <select name="center" onchange="this.form.submit()" style="width:auto">
      <option value="">Tous les centres</option>
      <?php foreach ($centers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $centerFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($n = pending_suggestions_count()): ?>
  <div class="flash flash-info"><?= icon('sparkles') ?><div>Il y a aussi <strong><?= plural($n, 'article hors catalogue', 'articles hors catalogue') ?></strong> à examiner : une fois ajoutés au catalogue, ils apparaîtront ici. <a href="<?= url('admin/suggestions') ?>">Voir les propositions →</a></div></div>
<?php endif; ?>
<?php if (!$bySupplier): ?>
  <div class="card"><div class="empty"><?= icon('check-circle') ?><h3>Aucune demande en attente</h3><p>Tout est traité ! Les nouvelles demandes des centres apparaîtront ici.</p></div></div>
<?php endif; ?>

<div class="stack">
<?php foreach ($bySupplier as $sid => $s):
  $supDeadlines = array_filter($deadlines, fn($d) => (int)$d['supplier_id'] === (int)$sid);
?>
  <div class="card supplier-block" style="border-left-color:<?= e($s['color']) ?>">
    <div class="card-head">
      <h2><?= icon('truck') ?> <?= e($s['name']) ?></h2>
      <div class="row">
        <?php foreach (array_slice($supDeadlines, 0, 1) as $d): $cd = countdown($d['deadline_at']); ?><span class="countdown <?= e($cd['level']) ?>"><?= icon('clock', 14) ?> <?= e($d['title']) ?> : <?= date_fr($d['deadline_at']) ?></span><?php endforeach; ?>
        <span class="badge badge-violet">Total <?= money($s['total']) ?></span>
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/supplier', ['id' => $sid]) ?>"><?= icon('settings', 15) ?></a>
      </div>
    </div>
    <?php foreach ($s['groups'] as $g):
      $min = $g['min_order_amount']; $pct = $min > 0 ? min(100, $g['total'] / $min * 100) : 100; $reached = $min <= 0 || $g['total'] >= $min;
      $formId = 'g' . $g['supplier_id'] . '-' . $g['center_id'];
    ?>
    <form method="post" action="<?= url('admin/po/create') ?>" id="<?= $formId ?>" data-po-group>
      <?= csrf_field() ?>
      <input type="hidden" name="supplier_id" value="<?= $g['supplier_id'] ?>">
      <input type="hidden" name="center_id" value="<?= $g['center_id'] ?>">
      <div class="group-head">
        <span class="badge" style="background:<?= e($g['center_color']) ?>;color:#fff"><?= icon('building', 14) ?> <?= e($g['center_name']) ?></span>
        <?= $g['urgent'] ? '<span class="badge badge-red">Contient de l\'urgent</span>' : '' ?>
        <div class="min-info">
          <div class="row"><strong data-sel-total><?= money($g['total']) ?></strong><small><?= $min > 0 ? 'Minimum fournisseur ' . money($min) : 'Pas de minimum' ?><?= $g['free_shipping_from'] > 0 ? ' · franco ' . money($g['free_shipping_from']) : '' ?></small></div>
          <div class="progress <?= $reached ? 'ok' : 'warn' ?>"><span style="width:<?= $pct ?>%"></span></div>
        </div>
        <span class="spacer"></span>
        <?php $bud = $budgetCache[$g['center_id']] ??= budget_status($g['center_id']); if ($bud['defined'] && $bud['remaining'] < $g['total']): ?><span class="badge badge-red" title="Budget <?= date('Y') ?> du centre"><?= icon('wallet', 13) ?> Budget dépassé (reste <?= money($bud['remaining']) ?>)</span><?php endif; ?>
        <?php if (!$reached): ?><span class="badge badge-amber" title="Vous pouvez attendre d'autres demandes ou créer le bon quand même"><?= icon('alert', 13) ?> Minimum non atteint (<?= money($min - $g['total']) ?> manquants)</span><?php endif; ?>
        <button class="btn btn-primary btn-sm" type="submit" <?= !$reached ? 'data-confirm="Le minimum de commande n\'est pas atteint. Créer le bon quand même ?"' : '' ?>><?= icon('file', 16) ?> Créer le bon de commande</button>
      </div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th style="width:36px"><input type="checkbox" checked data-toggle-all></th><th>Article</th><th>Demandeur</th><th class="num">Qté</th><th class="num">Prix u.</th><th class="num">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($g['lines'] as $l): ?>
          <tr>
            <td><input type="checkbox" name="lines[]" value="<?= (int)$l['id'] ?>" checked data-amount="<?= (float)$l['qty'] * (float)$l['unit_price'] ?>"></td>
            <td><div class="row"><?php partial('thumb', ['p' => $l + ['supplier_color' => $l['supplier_color']], 'size' => 36]); ?><div><div class="strong"><?= e($l['product_name']) ?></div><small><?= $l['reference'] ? 'Réf. ' . e($l['reference']) . ' · ' : '' ?><?= e($l['unit']) ?><?= $l['comment'] ? ' · « ' . e($l['comment']) . ' »' : '' ?></small></div></div></td>
            <td><div><?= e($l['first_name'] . ' ' . $l['last_name']) ?> <?= $l['urgent'] ? '<span class="badge badge-red">Urgent</span>' : '' ?></div><small><?= e($l['job']) ?> · <?= date_fr($l['created_at']) ?></small><?php if ($l['request_comment']): ?><div><small class="muted" title="Commentaire de la demande">💬 <?= e($l['request_comment']) ?></small></div><?php endif; ?></td>
            <td class="num"><?= (int)$l['qty'] ?></td>
            <td class="num"><?= money($l['unit_price']) ?></td>
            <td class="num strong"><?= money($l['qty'] * $l['unit_price']) ?></td>
            <td class="text-right"><button class="btn btn-ghost btn-sm btn-danger" type="submit" form="refuse-<?= (int)$l['id'] ?>" title="Refuser cette ligne"><?= icon('x', 15) ?></button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="card-body" style="padding-top:.6rem;padding-bottom:.8rem">
        <input type="text" name="notes" placeholder="Note interne / instructions pour ce bon (optionnel)">
      </div>
    </form>
    <?php foreach ($g['lines'] as $l): ?>
      <form method="post" action="<?= url('admin/requests/refuse', ['id' => $l['id']]) ?>" id="refuse-<?= (int)$l['id'] ?>" data-refuse class="hidden"><?= csrf_field() ?><input type="hidden" name="reason" value=""></form>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>
