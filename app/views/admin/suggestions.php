<div class="page-head"><div><h1>Articles proposés</h1><p>Articles hors catalogue proposés par les salariés (panier ou code-barres inconnu). Complétez-les et ajoutez-les au catalogue en un clic.</p></div></div>
<div class="tabs">
  <?php foreach (SUGGESTION_STATUSES as $k => $st): ?>
    <a class="<?= $status === $k ? 'active' : '' ?>" href="<?= url('admin/suggestions', ['status' => $k]) ?>"><?= e($st['label']) ?> <span class="badge badge-<?= $st['color'] ?>"><?= (int)($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
  <a class="<?= $status === 'all' ? 'active' : '' ?>" href="<?= url('admin/suggestions', ['status' => 'all']) ?>">Toutes</a>
</div>
<div class="card">
  <?php if ($items): ?><div class="table-wrap"><table class="table">
    <thead><tr><th></th><th>Article proposé</th><th>Par</th><th>Origine</th><th class="num">Qté demandée</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $o): ?>
      <tr>
        <td style="width:52px"><div class="thumb" style="width:40px;height:40px;background:linear-gradient(135deg,#ec4899,#8b5cf6)"><?php if ($o['image']): ?><img src="<?= e(product_image_url($o['image'])) ?>" alt=""><?php else: ?><?= icon('sparkles', 18) ?><?php endif; ?></div></td>
        <td><a class="strong" href="<?= url('admin/suggestion', ['id' => $o['id']]) ?>"><?= e($o['name']) ?></a><div><small><?= e(implode(' · ', array_filter([$o['brand'], $o['supplier_hint'], $o['barcode'] ? 'EAN ' . $o['barcode'] : null]))) ?></small></div></td>
        <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?><div><small><span class="dot" style="background:<?= e($o['center_color']) ?>"></span> <?= e($o['center_name']) ?> · <?= date_fr($o['created_at']) ?></small></div></td>
        <td><?= $o['source'] === 'scan' ? '<span class="badge badge-blue">' . icon('barcode', 12) . ' Scan</span>' : '<span class="badge badge-violet">' . icon('cart', 12) . ' Panier</span>' ?></td>
        <td class="num"><?= $o['qty'] ? '<strong>' . (int)$o['qty'] . '</strong>' : '<small class="muted">—</small>' ?></td>
        <td><?= badge(SUGGESTION_STATUSES[$o['status']]) ?><?= $o['product_name'] ? '<div><small>→ ' . e($o['product_name']) . '</small></div>' : '' ?></td>
        <td><a class="btn btn-sm <?= $o['status'] === 'pending' ? 'btn-primary' : '' ?>" href="<?= url('admin/suggestion', ['id' => $o['id']]) ?>"><?= $o['status'] === 'pending' ? 'Traiter' : 'Voir' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div><?php else: ?><div class="empty"><?= icon('sparkles') ?><p>Aucune proposition.</p></div><?php endif; ?>
</div>
