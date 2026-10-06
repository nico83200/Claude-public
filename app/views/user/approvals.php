<div class="page-head">
  <div><h1>Validations</h1><p>Demandes dépassant <?= money($threshold) ?> HT, à valider avant leur envoi au service achats. Vous pouvez ajuster les quantités.</p></div>
</div>
<?php if (!$requests): ?>
  <div class="card"><div class="empty"><?= icon('check-circle') ?><h3>Aucune demande à valider</h3><p>Vous serez notifié dès qu'une demande nécessitera votre accord.</p></div></div>
<?php endif; ?>
<div class="stack">
<?php foreach ($requests as $r): ?>
  <form method="post" action="<?= url('approvals/decide', ['id' => $r['id']]) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-head">
      <div class="row">
        <div class="avatar sm"><?= e(initials($r['first_name'], $r['last_name'])) ?></div>
        <div><h3 class="mb-0">Demande n°<?= (int)$r['id'] ?> — <?= e($r['first_name'] . ' ' . $r['last_name']) ?> <?= $r['urgent'] ? '<span class="badge badge-red">Urgent</span>' : '' ?></h3>
          <small><span class="dot" style="background:<?= e($r['center_color']) ?>"></span> <?= e($r['center_name']) ?> · <?= e($r['job']) ?> · <?= date_fr($r['created_at'], true) ?></small></div>
      </div>
      <strong style="font-size:1.15rem"><?= money($r['total']) ?></strong>
    </div>
    <?php if ($r['comment']): ?><div class="card-body" style="padding-bottom:0"><small><?= icon('info', 14) ?> <?= e($r['comment']) ?></small></div><?php endif; ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Article</th><th>Fournisseur</th><th class="num">Qté</th><th class="num">Prix u.</th><th class="num">Total</th></tr></thead>
      <tbody><?php foreach ($r['lines'] as $l): ?>
        <tr>
          <td><div class="row"><?php partial('thumb', ['p' => $l, 'size' => 34]); ?><div><div class="strong"><?= e($l['name']) ?></div><small><?= e($l['unit']) ?><?= $l['comment'] ? ' · « ' . e($l['comment']) . ' »' : '' ?></small></div></div></td>
          <td><span class="dot" style="background:<?= e($l['supplier_color']) ?>"></span> <?= e($l['supplier_name']) ?></td>
          <td class="num"><input class="qty-input" type="number" min="0" name="qty[<?= (int)$l['id'] ?>]" value="<?= (int)$l['qty'] ?>" title="0 = retirer"></td>
          <td class="num"><?= money($l['unit_price']) ?></td>
          <td class="num strong"><?= money($l['qty'] * $l['unit_price']) ?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <div class="card-body">
      <?php if ($r['budget']['defined']): ?><div class="mb-2"><small class="muted"><?= icon('wallet', 14) ?> Budget <?= date('Y') ?> du centre</small><?php partial('budget_gauge', ['b' => $r['budget']]); ?></div><?php endif; ?>
      <div class="row row-wrap">
        <input type="text" name="note" placeholder="Message au salarié (optionnel)" style="flex:1;min-width:220px">
        <button class="btn btn-danger" type="submit" name="decision" value="reject" onclick="return confirm('Refuser toute la demande ?')"><?= icon('x', 16) ?> Refuser</button>
        <button class="btn btn-success" type="submit" name="decision" value="approve"><?= icon('check', 16) ?> Valider</button>
      </div>
    </div>
  </form>
<?php endforeach; ?>
</div>
<?php if ($history): ?>
<div class="card mt-3">
  <div class="card-head"><h3><?= icon('clock', 18) ?> Dernières décisions</h3></div>
  <ul class="list"><?php foreach ($history as $h): ?>
    <li><div class="grow"><div class="title">Demande n°<?= (int)$h['id'] ?> — <?= e($h['first_name'] . ' ' . $h['last_name']) ?> (<?= e($h['center_name']) ?>)</div><small><?= date_fr($h['approved_at'], true) ?> par <?= e($h['a_first'] . ' ' . $h['a_last']) ?><?= $h['approval_note'] ? ' · « ' . e($h['approval_note']) . ' »' : '' ?></small></div>
    <?= $h['approval_status'] === 'approved' ? '<span class="badge badge-green">Validée</span>' : '<span class="badge badge-gray">Refusée</span>' ?></li>
  <?php endforeach; ?></ul>
</div>
<?php endif; ?>
