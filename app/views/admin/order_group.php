<?php $first = $pos[0]; $min = (float)$first['min_order_amount']; $toOrder = count(array_filter($pos, fn($p) => $p['status'] === 'a_commander')); ?>
<div class="breadcrumb"><a href="<?= url('admin/orders', ['status' => 'open']) ?>">Bons de commande</a> <?= icon('chevron-right', 14) ?> <?= e($ref) ?></div>
<div class="page-head">
  <div><h1>Commande groupée <?= e($ref) ?></h1><p><strong><?= e($first['supplier_name']) ?></strong> · <?= count($pos) ?> centres livrés · un bon par centre, une seule commande fournisseur</p></div>
  <div class="row row-wrap">
    <a class="btn" href="<?= url('admin/order/pdf', ['ref' => $ref]) ?>" target="_blank"><?= icon('file', 18) ?> PDF groupé</a>
  </div>
</div>
<div class="grid grid-main">
  <div class="card">
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Bon</th><th>Centre livré</th><th class="num">Lignes</th><th class="num">Montant HT</th><th class="num">Port</th><th>Statut</th></tr></thead>
      <tbody><?php foreach ($pos as $po): ?>
        <tr><td><a class="strong" href="<?= url('admin/order', ['id' => $po['id']]) ?>"><?= e($po['po_number']) ?></a></td><td><span class="dot" style="background:<?= e($po['center_color']) ?>"></span> <?= e($po['center_name']) ?></td>
          <td class="num"><?= (int)$po['totals']['lines'] ?></td><td class="num"><?= money($po['totals']['total']) ?></td><td class="num"><?= money($po['shipping_fee']) ?></td><td><?= po_status_badge($po['status']) ?></td></tr>
      <?php endforeach; ?></tbody>
      <tfoot><tr><td colspan="3" class="text-right">Total de la commande</td><td class="num"><?= money($total) ?></td><td class="num"><?= money($shipping) ?></td><td></td></tr></tfoot>
    </table></div>
    <div class="card-body">
      <?php $pct = $min > 0 ? min(100, $total / $min * 100) : 100; ?>
      <div class="row" style="justify-content:space-between;font-size:.85rem"><span>Minimum fournisseur : <?= $min > 0 ? money($min) : 'aucun' ?><?= (float)$first['free_shipping_from'] > 0 ? ' · franco ' . money($first['free_shipping_from']) : '' ?></span><strong><?= round($pct) ?> %</strong></div>
      <div class="progress <?= $pct >= 100 ? 'ok' : 'warn' ?> mt-1"><span style="width:<?= $pct ?>%"></span></div>
      <small class="muted">Le minimum et le franco sont appréciés sur le total groupé ; les frais de port éventuels sont portés par le premier bon.</small>
    </div>
  </div>
  <div class="stack">
    <?php if ($toOrder): ?>
    <form method="post" action="<?= url('admin/order/send', ['ref' => $ref]) ?>" class="card card-body">
      <?= csrf_field() ?>
      <h3><?= icon('send', 18) ?> Envoyer au fournisseur</h3>
      <div class="field"><label>Destinataire</label><input type="email" name="to" value="<?= e($first['supplier_email']) ?>" required></div>
      <label class="check"><input type="checkbox" name="mark_ordered" value="1" checked> Passer tous les bons en « Commandé »</label>
      <label class="check"><input type="checkbox" name="cc_me" value="1"> M'envoyer une copie</label>
      <button class="btn btn-blue mt-1" type="submit"><?= icon('send', 16) ?> Envoyer le PDF groupé</button>
    </form>
    <form method="post" action="<?= url('admin/order-group/ordered', ['ref' => $ref]) ?>" class="card card-body">
      <?= csrf_field() ?>
      <h3><?= icon('check', 18) ?> Commandé par un autre moyen</h3>
      <div class="field"><label>Réf. fournisseur</label><input type="text" name="supplier_reference"></div>
      <div class="field"><label>Livraison prévue le</label><input type="date" name="expected_date"></div>
      <button class="btn" type="submit">Marquer les <?= $toOrder ?> bons « Commandé »</button>
    </form>
    <?php else: ?>
      <div class="card card-body"><p class="mb-0">Tous les bons du groupe sont commandés. Chaque centre réceptionne son propre bon.</p></div>
    <?php endif; ?>
  </div>
</div>
