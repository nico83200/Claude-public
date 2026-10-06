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
<div class="card">
  <?php if ($moves): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Date</th><?php if (!$product): ?><th>Article</th><?php endif; ?><th>Type</th><th class="num">Variation</th><th class="num">Stock après</th><th>Par</th><th>Motif</th></tr></thead>
    <tbody>
    <?php foreach ($moves as $m): $t = STOCK_MOVE_TYPES[$m['type']] ?? ['label' => $m['type'], 'color' => 'gray']; ?>
      <tr>
        <td class="nowrap"><?= date_fr($m['created_at'], true) ?></td>
        <?php if (!$product): ?><td><a href="<?= url('stock/history', ['product_id' => $m['product_id']]) ?>"><?= e($m['name']) ?></a></td><?php endif; ?>
        <td><?= badge($t) ?></td>
        <td class="num strong" style="color:<?= $m['delta'] > 0 ? 'var(--green)' : ($m['delta'] < 0 ? 'var(--red)' : 'var(--muted)') ?>"><?= $m['delta'] > 0 ? '+' : '' ?><?= (int)$m['delta'] ?></td>
        <td class="num"><?= (int)$m['qty_after'] ?></td>
        <td><small><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?: '—' ?></small></td>
        <td><small><?= e($m['note']) ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><div class="empty"><?= icon('clock') ?><p>Aucun mouvement enregistré.</p></div><?php endif; ?>
</div>
