<div class="page-head">
  <div><h1>Suivi des demandes</h1><p><?= e($center['name']) ?></p></div>
</div>
<div class="tabs">
  <a class="<?= $scope === 'mine' ? 'active' : '' ?>" href="<?= url('requests', ['scope' => 'mine', 'status' => $status]) ?>"><?= icon('users', 16) ?> Mes demandes</a>
  <a class="<?= $scope === 'center' ? 'active' : '' ?>" href="<?= url('requests', ['scope' => 'center', 'status' => $status]) ?>"><?= icon('building', 16) ?> Tout le centre</a>
</div>
<div class="chips mb-2">
  <?php foreach (['' => 'Tous', 'pending' => 'En attente', 'ordered' => 'Commandés', 'received' => 'Reçus'] as $k => $lbl): ?>
    <a class="chip <?= $status === $k ? 'active' : '' ?>" href="<?= url('requests', ['scope' => $scope, 'status' => $k]) ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$requests): ?>
  <div class="card"><div class="empty"><?= icon('clipboard') ?><h3>Aucune demande</h3><p>Vos demandes apparaîtront ici avec leur avancement.</p><a class="btn btn-primary" href="<?= url('catalog') ?>">Faire une demande</a></div></div>
<?php endif; ?>

<div class="stack">
<?php foreach ($requests as $r): ?>
  <div class="card">
    <div class="card-head">
      <div class="row">
        <div class="avatar sm"><?= e(initials($r['first_name'], $r['last_name'])) ?></div>
        <div>
          <h3 class="mb-0">Demande n°<?= (int)$r['id'] ?> <?= $r['urgent'] ? '<span class="badge badge-red">Urgent</span>' : '' ?></h3>
          <small><?= e($r['first_name'] . ' ' . $r['last_name']) ?><?= $r['job'] ? ' · ' . e($r['job']) : '' ?> · <?= date_fr($r['created_at'], true) ?></small>
        </div>
      </div>
      <form method="post" action="<?= url('request/reorder', ['id' => $r['id']]) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit" title="Remettre ces articles dans le panier"><?= icon('repeat', 16) ?> Recommander</button></form>
    </div>
    <?php if ($r['comment']): ?><div class="card-body" style="padding-bottom:0"><small><?= icon('info', 14) ?> <?= e($r['comment']) ?></small></div><?php endif; ?>
    <div class="table-wrap"><table class="table">
      <tbody>
      <?php foreach ($r['lines'] as $l): $st = request_line_status($l); ?>
        <tr class="<?= $l['status'] === 'cancelled' ? 'is-done' : '' ?>">
          <td style="width:52px"><?php partial('thumb', ['p' => $l + ['supplier_color' => $l['supplier_color']], 'size' => 38]); ?></td>
          <td>
            <div class="strong"><?= e($l['name']) ?></div>
            <small><span class="dot" style="background:<?= e($l['supplier_color']) ?>;width:7px;height:7px"></span> <?= e($l['supplier_name']) ?><?= $l['comment'] ? ' · « ' . e($l['comment']) . ' »' : '' ?></small>
            <?php if ($l['status'] === 'cancelled' && $l['cancel_reason']): ?><div><small class="muted"><?= e($l['cancel_reason']) ?></small></div><?php endif; ?>
          </td>
          <td class="nowrap">× <?= (int)$l['qty'] ?></td>
          <td><?= badge($st) ?><?php if ($l['po_number']): ?><div><small><?= e($l['po_number']) ?></small></div><?php endif; ?></td>
          <td class="text-right">
            <?php if ($l['status'] === 'pending' && ((int)$r['user_id'] === (int)user()['id'] || is_admin())): ?>
              <form method="post" action="<?= url('request/cancel-line', ['id' => $l['id']]) ?>" onsubmit="return confirm('Annuler cette ligne ?')"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-danger" type="submit"><?= icon('x', 16) ?> Annuler</button></form>
            <?php elseif (in_array($l['po_status'], ['commande', 'partiel'], true)): ?>
              <a class="btn btn-sm btn-success" href="<?= url('reception', ['id' => $l['purchase_order_id']]) ?>"><?= icon('check', 16) ?> Réception</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endforeach; ?>
</div>
