<div class="page-head"><div><h1>Rapprochement des factures</h1><p>Comparez chaque facture fournisseur aux marchandises réellement reçues (tolérance : <?= money(setting('invoice_tolerance', '1')) ?>).</p></div>
  <a class="btn" href="<?= url('admin/exports') ?>"><?= icon('download', 18) ?> Exporter</a></div>
<div class="tabs">
  <a class="<?= $filter === 'todo' ? 'active' : '' ?>" href="<?= url('admin/invoices', ['filter' => 'todo']) ?>">À saisir <span class="badge badge-amber"><?= $counts['todo'] ?></span></a>
  <a class="<?= $filter === 'ecart' ? 'active' : '' ?>" href="<?= url('admin/invoices', ['filter' => 'ecart']) ?>">Écarts <span class="badge badge-red"><?= $counts['ecart'] ?></span></a>
  <a class="<?= $filter === 'ok' ? 'active' : '' ?>" href="<?= url('admin/invoices', ['filter' => 'ok']) ?>">Conformes</a>
  <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="<?= url('admin/invoices', ['filter' => 'all']) ?>">Tous</a>
</div>
<div class="card">
  <?php if ($orders): ?><div class="table-wrap"><table class="table">
    <thead><tr><th>Bon</th><th>Fournisseur</th><th>Centre</th><th class="num">Commandé</th><th class="num">Reçu</th><th class="num">Facturé</th><th class="num">Écart</th><th>Statut</th><th></th></tr></thead>
    <tbody><?php foreach ($orders as $o): $c = $o['check']; ?>
      <tr>
        <td class="strong"><a href="<?= url('admin/order', ['id' => $o['id']]) ?>"><?= e($o['po_number']) ?></a><div><small><?= date_fr($o['ordered_at']) ?></small></div></td>
        <td><span class="dot" style="background:<?= e($o['supplier_color']) ?>"></span> <?= e($o['supplier_name']) ?></td>
        <td><?= e($o['center_name']) ?></td>
        <td class="num"><?= money($c['ordered']) ?></td>
        <td class="num"><?= money($c['expected']) ?></td>
        <td class="num"><?= $o['invoice_amount'] !== null ? money($o['invoice_amount']) . ($o['invoice_number'] ? '<div><small>' . e($o['invoice_number']) . '</small></div>' : '') : '—' ?></td>
        <td class="num"><?= $c['diff'] !== null ? '<strong style="color:' . (abs($c['diff']) > 0.01 ? 'var(--red)' : 'var(--green)') . '">' . ($c['diff'] > 0 ? '+' : '') . money($c['diff']) . '</strong>' : '' ?></td>
        <td><?= invoice_badge($c['status']) ?></td>
        <td><a class="btn btn-sm" href="<?= url('admin/order', ['id' => $o['id']]) ?>#facture"><?= $c['status'] === 'none' ? 'Saisir' : 'Voir' ?></a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div><?php else: ?><div class="empty"><?= icon('check-circle') ?><p>Rien à afficher.</p></div><?php endif; ?>
</div>
