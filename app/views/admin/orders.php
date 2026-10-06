<div class="page-head">
  <div><h1>Bons de commande</h1><p>Suivi de tous les bons, du statut « à commander » jusqu'à la réception.</p></div>
  <a class="btn btn-primary" href="<?= url('admin/requests') ?>"><?= icon('plus', 18) ?> Créer depuis les demandes</a>
</div>
<div class="tabs">
  <a class="<?= $status === 'open' ? 'active' : '' ?>" href="<?= url('admin/orders', ['status' => 'open']) ?>">En cours</a>
  <?php foreach (PO_STATUSES as $k => $s): ?>
    <a class="<?= $status === $k ? 'active' : '' ?>" href="<?= url('admin/orders', ['status' => $k]) ?>"><?= e($s['label']) ?> <span class="badge badge-<?= $s['color'] ?>"><?= (int)($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
  <a class="<?= $status === '' ? 'active' : '' ?>" href="<?= url('admin/orders', ['status' => '']) ?>">Tous</a>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="admin/orders"><input type="hidden" name="status" value="<?= e($status) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="N° de bon ou réf. fournisseur">
  <select name="center"><option value="">Tous les centres</option><?php foreach ($centers as $c): ?><option value="<?= $c['id'] ?>" <?= $center === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="supplier"><option value="">Tous les fournisseurs</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= $supplier === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  <button class="btn" type="submit"><?= icon('filter', 16) ?> Filtrer</button>
</form>
<div class="card">
  <?php if ($orders): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>N°</th><th>Fournisseur</th><th>Centre</th><th>Créé</th><th>Commandé</th><th class="num">Montant HT</th><th>Réception</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): $t = $o['totals']; ?>
      <tr>
        <td class="strong nowrap"><a href="<?= url('admin/order', ['id' => $o['id']]) ?>"><?= e($o['po_number']) ?></a><?php if ($o['group_ref']): ?><div><a class="badge badge-blue" href="<?= url('admin/order-group', ['ref' => $o['group_ref']]) ?>"><?= icon('layers', 11) ?> <?= e($o['group_ref']) ?></a></div><?php endif; ?></td>
        <td><span class="dot" style="background:<?= e($o['supplier_color']) ?>"></span> <?= e($o['supplier_name']) ?></td>
        <td><span class="dot" style="background:<?= e($o['center_color']) ?>"></span> <?= e($o['center_name']) ?></td>
        <td class="nowrap"><?= date_fr($o['created_at']) ?></td>
        <td class="nowrap"><?= date_fr($o['ordered_at']) ?></td>
        <td class="num strong"><?= money($t['total'] + $o['shipping_fee']) ?></td>
        <td style="min-width:110px"><?php if (in_array($o['status'], ['commande', 'partiel', 'recu'], true)): $pct = $t['qty'] ? $t['received'] / $t['qty'] * 100 : 0; ?><div class="progress <?= $pct >= 100 ? 'ok' : '' ?>"><span style="width:<?= $pct ?>%"></span></div><small><?= $t['received'] ?>/<?= $t['qty'] ?></small><?php else: ?><small class="muted">—</small><?php endif; ?></td>
        <td><?= po_status_badge($o['status']) ?><?php if (in_array($o['status'], ['partiel', 'recu'], true)): ?><div class="mt-1"><?= invoice_badge($o['invoice_status'] ?: null) ?></div><?php endif; ?></td>
        <td><a class="btn btn-sm" href="<?= url('admin/order', ['id' => $o['id']]) ?>">Ouvrir</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><div class="empty"><?= icon('file') ?><p>Aucun bon de commande pour ces critères.</p></div><?php endif; ?>
</div>
