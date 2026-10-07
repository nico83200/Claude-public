<?php $editable = in_array($po['status'], ['commande', 'partiel', 'recu'], true); ?>
<div class="breadcrumb"><?php if (is_admin()): ?><a href="<?= url('admin/orders') ?>">Bons de commande</a> <?= icon('chevron-right', 14) ?> <a href="<?= url('admin/order', ['id' => $po['id']]) ?>"><?= e($po['po_number']) ?></a> <?= icon('chevron-right', 14) ?> Réception<?php else: ?><a href="<?= url('receptions') ?>">Réceptions</a> <?= icon('chevron-right', 14) ?> <?= e($po['po_number']) ?><?php endif; ?></div>
<div class="page-head">
  <div>
    <h1><?= e($po['supplier_name']) ?> <?= po_status_badge($po['status']) ?></h1>
    <p>Bon <?= e($po['po_number']) ?> · <?= e($po['center_name']) ?><?= $po['ordered_at'] ? ' · commandé le ' . date_fr($po['ordered_at']) : '' ?><?= $po['supplier_reference'] ? ' · réf. fournisseur ' . e($po['supplier_reference']) : '' ?></p>
  </div>
  <div style="min-width:260px">
    <div class="row" style="justify-content:space-between"><small>Avancement</small><strong><?= $totals['received'] ?>/<?= $totals['qty'] ?></strong></div>
    <?php $pct = $totals['qty'] ? round($totals['received'] / $totals['qty'] * 100) : 0; ?>
    <div class="progress <?= $pct >= 100 ? 'ok' : '' ?>"><span style="width:<?= $pct ?>%"></span></div>
  </div>
</div>

<?php if (!$editable): ?>
  <div class="flash flash-info"><?= icon('info') ?><div>Ce bon n'a pas encore été transmis au fournisseur : la réception sera possible une fois qu'il sera « Commandé ».</div></div>
<?php endif; ?>

<div class="grid grid-main">
  <form method="post" action="<?= url('reception/save', ['id' => $po['id']]) ?>" class="card" id="recv-form">
    <?= csrf_field() ?>
    <div class="card-head">
      <h2><?= icon('package-check') ?> Articles livrés</h2>
      <?php if ($editable): ?><button class="btn btn-sm" type="button" data-check-all><?= icon('check', 16) ?> Tout cocher</button><?php endif; ?>
    </div>
    <?php foreach ($lines as $l): $full = (int)$l['qty_received'] >= (int)$l['qty']; ?>
      <div class="recv-line <?= $full ? 'ok' : '' ?>" data-line>
        <input class="big-check" type="checkbox" data-full="<?= (int)$l['qty'] ?>" <?= $full ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?> aria-label="Reçu en totalité">
        <?php partial('thumb', ['p' => $l, 'size' => 44]); ?>
        <div class="grow">
          <div class="strong"><?= e($l['label']) ?></div>
          <small><?= $l['reference'] ? 'Réf. ' . e($l['reference']) . ' · ' : '' ?><?= e($l['unit']) ?></small>
          <?php if (!empty($requesters[(int)$l['product_id']])): ?>
            <div><small class="muted">Pour : <?= e(implode(', ', array_map(fn($r) => $r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['qty'] . ')', $requesters[(int)$l['product_id']]))) ?></small></div>
          <?php endif; ?>
          <?php if ($l['received_at']): ?><div><small class="muted">Reçu le <?= date_fr($l['received_at'], true) ?><?= $l['first_name'] ? ' par ' . e($l['first_name'] . ' ' . $l['last_name']) : '' ?></small></div><?php endif; ?>
        </div>
        <div class="row nowrap">
          <input class="qty-input" type="number" min="0" max="<?= (int)$l['qty'] ?>" name="received[<?= (int)$l['id'] ?>]" value="<?= (int)$l['qty_received'] ?>" <?= $editable ? '' : 'disabled' ?>>
          <span class="muted">/ <?= (int)$l['qty'] ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if ($editable): ?>
    <div class="card-foot row">
      <small class="muted">Cochez les articles reçus en totalité, ou saisissez la quantité réellement livrée.</small>
      <span class="spacer"></span>
      <button class="btn btn-success" type="submit"><?= icon('check', 18) ?> Enregistrer la réception</button>
    </div>
    <?php endif; ?>
  </form>

  <div class="card">
    <div class="card-head"><h3><?= icon('clock', 18) ?> Historique</h3></div>
    <ul class="timeline">
      <?php foreach ($history as $h): ?>
        <li><div class="strong"><?= e($h['action']) ?></div><?php if ($h['details']): ?><small><?= e($h['details']) ?></small><?php endif; ?><div class="when"><?= date_fr($h['created_at'], true) ?><?= $h['first_name'] ? ' · ' . e($h['first_name'] . ' ' . $h['last_name']) : '' ?></div></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
