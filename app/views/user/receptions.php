<div class="page-head">
  <div><h1>Réceptions</h1><p>Confirmez les livraisons reçues à <strong><?= e($center['name']) ?></strong>, en totalité ou en partie.</p></div>
</div>

<div class="card mb-2">
  <div class="card-head"><h2><?= icon('truck') ?> Commandes en attente de livraison</h2><span class="badge badge-blue"><?= count($open) ?></span></div>
  <?php if ($open): ?>
  <ul class="list">
    <?php foreach ($open as $po): $pct = $po['totals']['qty'] ? round($po['totals']['received'] / $po['totals']['qty'] * 100) : 0; ?>
    <li>
      <div class="stat-icon g-blue" style="width:42px;height:42px;border-radius:12px"><?= icon('truck', 20) ?></div>
      <div class="grow">
        <div class="title"><?= e($po['supplier_name']) ?> · <?= e($po['po_number']) ?></div>
        <small>Commandé le <?= date_fr($po['ordered_at']) ?><?= $po['expected_date'] ? ' · prévue le ' . date_fr($po['expected_date']) : '' ?><?= $po['delivery_delay'] ? ' · délai ' . e($po['delivery_delay']) : '' ?></small>
        <div class="progress <?= $pct >= 100 ? 'ok' : '' ?> mt-1" style="max-width:280px"><span style="width:<?= $pct ?>%"></span></div>
      </div>
      <small class="nowrap"><?= $po['totals']['received'] ?>/<?= $po['totals']['qty'] ?> reçus</small>
      <?= po_status_badge($po['status']) ?>
      <a class="btn btn-success btn-sm" href="<?= url('reception', ['id' => $po['id']]) ?>"><?= icon('check', 16) ?> Réceptionner</a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
    <div class="empty"><?= icon('package-check') ?><p>Aucune livraison en attente. 🎉</p></div>
  <?php endif; ?>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h3><?= icon('clock', 18) ?> Validées, bientôt commandées</h3></div>
    <?php if ($upcoming): ?><ul class="list"><?php foreach ($upcoming as $po): ?>
      <li><span class="dot" style="background:<?= e($po['supplier_color']) ?>"></span><div class="grow"><div class="title"><?= e($po['supplier_name']) ?></div><small><?= e($po['po_number']) ?> · créé le <?= date_fr($po['created_at']) ?></small></div><?= po_status_badge($po['status']) ?></li>
    <?php endforeach; ?></ul><?php else: ?><div class="empty"><p>Rien en préparation.</p></div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('check-circle', 18) ?> Dernières réceptions</h3></div>
    <?php if ($done): ?><ul class="list"><?php foreach ($done as $po): ?>
      <li><span class="dot" style="background:<?= e($po['supplier_color']) ?>"></span><div class="grow"><a class="title" href="<?= url('reception', ['id' => $po['id']]) ?>"><?= e($po['supplier_name']) ?></a><div><small><?= e($po['po_number']) ?> · reçu le <?= date_fr($po['received_at']) ?></small></div></div><?= po_status_badge($po['status']) ?></li>
    <?php endforeach; ?></ul><?php else: ?><div class="empty"><p>Aucune réception enregistrée.</p></div><?php endif; ?>
  </div>
</div>
