<?php $maxMonth = max(1, max(array_column($monthly, 'total'))); ?>
<div class="page-head">
  <div><h1>Pilotage des achats</h1><p><?= e(ucfirst(date_long_fr(date('Y-m-d')))) ?> · vue consolidée de tous les centres</p></div>
  <div class="row">
    <a class="btn" href="<?= url('admin/deadlines') ?>"><?= icon('calendar', 18) ?> Dates limites</a>
    <a class="btn btn-primary" href="<?= url('admin/requests') ?>"><?= icon('inbox', 18) ?> Traiter les demandes</a>
  </div>
</div>

<div class="grid grid-4 mb-2">
  <a class="stat c-pink" href="<?= url('admin/requests') ?>">
    <div class="stat-icon g-pink"><?= icon('inbox', 24) ?></div>
    <div><div class="stat-value"><?= $kpi['pending_lines'] ?></div><div class="stat-label">Lignes de demande à traiter<?= $kpi['urgent'] ? ' · <strong style="color:#e11d48">' . $kpi['urgent'] . ' urgente(s)</strong>' : '' ?></div></div>
  </a>
  <a class="stat c-amber" href="<?= url('admin/orders', ['status' => 'a_commander']) ?>">
    <div class="stat-icon g-amber"><?= icon('file', 24) ?></div>
    <div><div class="stat-value"><?= $kpi['to_order'] ?></div><div class="stat-label">Bons « à commander »</div></div>
  </a>
  <a class="stat c-blue" href="<?= url('admin/orders', ['status' => 'commande']) ?>">
    <div class="stat-icon g-blue"><?= icon('truck', 24) ?></div>
    <div><div class="stat-value"><?= $kpi['ordered'] ?></div><div class="stat-label">Commandes en cours de livraison</div></div>
  </a>
  <a class="stat c-green" href="<?= url('admin/orders') ?>">
    <div class="stat-icon g-green"><?= icon('euro', 24) ?></div>
    <div><div class="stat-value"><?= money($kpi['month_spend']) ?></div><div class="stat-label">Commandé ce mois (HT)</div></div>
  </a>
</div>

<?php if ($n = pending_suggestions_count()): ?>
<div class="flash flash-info"><?= icon('sparkles') ?><div><strong><?= plural($n, 'article hors catalogue proposé', 'articles hors catalogue proposés') ?></strong> par les centres. <a href="<?= url('admin/suggestions') ?>">Examiner →</a></div></div>
<?php endif; ?>
<?php if ($kpi['pending_users']): ?>
<div class="flash flash-info"><?= icon('users') ?><div><strong><?= plural($kpi['pending_users'], 'compte attend', 'comptes attendent') ?></strong> votre validation. <a href="<?= url('admin/users', ['status' => 'pending']) ?>">Valider maintenant →</a></div></div>
<?php endif; ?>

<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <h2><?= icon('chart') ?> Dépenses mensuelles</h2>
        <div class="row"><span class="badge badge-violet">Année : <?= money($kpi['year_spend']) ?></span><span class="badge badge-green">Économies négociées : <?= money($kpi['year_savings']) ?></span></div>
      </div>
      <div class="card-body">
        <div class="bars">
          <?php foreach ($monthly as $i => $m): ?>
            <div class="bar <?= $i === count($monthly) - 1 ? 'current' : '' ?>">
              <div class="bar-fill" style="height:<?= max(1.5, $m['total'] / $maxMonth * 100) ?>%" data-value="<?= e(money($m['total'])) ?>"></div>
              <div class="bar-label"><?= e($m['label']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= icon('inbox') ?> Demandes en attente par fournisseur</h2><a class="btn btn-sm" href="<?= url('admin/requests') ?>">Tout traiter</a></div>
      <?php if ($groups): ?>
      <ul class="list">
        <?php foreach ($groups as $g): $min = $g['min_order_amount']; $pct = $min > 0 ? min(100, $g['total'] / $min * 100) : 100; ?>
        <li>
          <span class="dot" style="background:<?= e($g['supplier_color']) ?>;width:12px;height:12px"></span>
          <div class="grow">
            <div class="title"><?= e($g['supplier_name']) ?> → <?= e($g['center_name']) ?> <?= $g['urgent'] ? '<span class="badge badge-red">Urgent</span>' : '' ?></div>
            <small><?= plural(count($g['lines']), 'ligne', 'lignes') ?> · depuis le <?= date_fr($g['oldest']) ?></small>
          </div>
          <div class="min-info">
            <div class="row"><strong><?= money($g['total']) ?></strong><small><?= $min > 0 ? 'minimum ' . money($min) : 'pas de minimum' ?></small></div>
            <div class="progress <?= $pct >= 100 ? 'ok' : 'warn' ?>"><span style="width:<?= $pct ?>%"></span></div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?><div class="empty"><?= icon('check-circle') ?><p>Toutes les demandes ont été traitées. Bravo !</p></div><?php endif; ?>
    </div>

    <?php if ($toOrder): ?>
    <div class="card">
      <div class="card-head"><h2><?= icon('file') ?> Bons à passer chez les fournisseurs</h2></div>
      <ul class="list">
        <?php foreach ($toOrder as $po): ?>
        <li>
          <span class="dot" style="background:<?= e($po['supplier_color']) ?>;width:12px;height:12px"></span>
          <div class="grow"><a class="title" href="<?= url('admin/order', ['id' => $po['id']]) ?>"><?= e($po['po_number']) ?> · <?= e($po['supplier_name']) ?></a><div><small><?= e($po['center_name']) ?> · créé le <?= date_fr($po['created_at']) ?></small></div></div>
          <strong><?= money($po['totals']['total'] + $po['shipping_fee']) ?></strong>
          <a class="btn btn-amber btn-sm" href="<?= url('admin/order', ['id' => $po['id']]) ?>">Ouvrir</a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('building') ?> Par centre (<?= date('Y') ?>)</h2></div>
      <div class="card-body">
        <?php $maxC = max(1, ...array_map(fn($c) => (float)$c['total'], $byCenter ?: [['total' => 1]])); ?>
        <?php foreach ($byCenter as $c): $b = budget_status((int)$c['id']); ?>
          <div class="hbar">
            <div class="hbar-head"><span><span class="dot" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?></span><strong><?= money($c['total']) ?></strong></div>
            <?php if ($b['defined']): ?>
              <?php partial('budget_gauge', ['b' => $b]); ?>
            <?php else: ?>
              <div class="progress"><span style="width:<?= (float)$c['total'] / $maxC * 100 ?>%;background:<?= e($c['color']) ?>"></span></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <a class="btn btn-sm mt-1" href="<?= url('admin/budgets') ?>"><?= icon('wallet', 15) ?> Gérer les budgets</a>
        <?php if (!$byCenter): ?><p class="muted">Aucun centre.</p><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= icon('truck') ?> Top fournisseurs</h2></div>
      <div class="card-body">
        <?php $maxS = max(1, ...array_map(fn($s) => (float)$s['total'], $bySupplier ?: [['total' => 1]])); ?>
        <?php foreach ($bySupplier as $s): ?>
          <div class="hbar">
            <div class="hbar-head"><span><span class="dot" style="background:<?= e($s['color']) ?>"></span><?= e($s['name']) ?></span><strong><?= money($s['total']) ?></strong></div>
            <div class="progress"><span style="width:<?= (float)$s['total'] / $maxS * 100 ?>%;background:<?= e($s['color']) ?>"></span></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$bySupplier): ?><p class="muted">Pas encore de commande cette année.</p><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= icon('calendar') ?> Prochaines dates limites</h2><a class="btn btn-sm" href="<?= url('admin/deadlines') ?>"><?= icon('plus', 15) ?></a></div>
      <?php if ($deadlines): foreach ($deadlines as $d): $cd = countdown($d['deadline_at']); ?>
        <div class="deadline">
          <div class="deadline-date lvl-<?= e($cd['level']) ?>"><b><?= date('d', strtotime($d['deadline_at'])) ?></b><span><?= e(month_short_fr((int)date('n', strtotime($d['deadline_at'])))) ?></span></div>
          <div style="flex:1;min-width:0"><div class="strong"><?= e($d['title']) ?></div><small><?= e($d['center_name'] ?: 'Tous les centres') ?><?= $d['supplier_name'] ? ' · ' . e($d['supplier_name']) : '' ?></small></div>
          <span class="countdown <?= e($cd['level']) ?>"><?= e($cd['label']) ?></span>
        </div>
      <?php endforeach; else: ?><div class="empty"><p>Aucune date limite programmée.</p></div><?php endif; ?>
    </div>

    <?php $incr = recent_price_increases(90, 6); if ($incr): ?>
    <div class="card">
      <div class="card-head"><h2><?= icon('chart') ?> Hausses de prix (90 j)</h2><a class="btn btn-sm" href="<?= url('admin/compare') ?>">Comparateur</a></div>
      <ul class="list"><?php foreach ($incr as $h): ?>
        <li><div class="grow"><a class="title" href="<?= url('admin/product', ['id' => $h['product_id']]) ?>"><?= e($h['name']) ?></a><small><?= e($h['supplier_name']) ?> · <?= money($h['old']) ?> → <?= money($h['new']) ?></small></div><span class="badge badge-red">+<?= $h['pct'] ?> %</span></li>
      <?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <?php $invTodo = (int)val("SELECT COUNT(*) FROM purchase_orders WHERE invoice_amount IS NULL AND status IN ('partiel','recu')"); $invGap = (int)val("SELECT COUNT(*) FROM purchase_orders WHERE invoice_status = 'ecart'"); if ($invTodo || $invGap): ?>
    <a class="stat c-amber" href="<?= url('admin/invoices', ['filter' => $invGap ? 'ecart' : 'todo']) ?>"><div class="stat-icon g-amber"><?= icon('euro', 24) ?></div><div><div class="stat-value"><?= $invTodo ?></div><div class="stat-label">Facture(s) à rapprocher<?= $invGap ? ' · <strong style="color:#dc2626">' . $invGap . ' écart(s)</strong>' : '' ?></div></div></a>
    <?php endif; ?>
    <?php if ($lateOrders): ?>
    <div class="card">
      <div class="card-head"><h2><?= icon('alert') ?> Livraisons en retard (&gt; 10 j)</h2></div>
      <ul class="list"><?php foreach ($lateOrders as $po): ?>
        <li><div class="grow"><a class="title" href="<?= url('admin/order', ['id' => $po['id']]) ?>"><?= e($po['supplier_name']) ?></a><div><small><?= e($po['po_number']) ?> · <?= e($po['center_name']) ?> · commandé le <?= date_fr($po['ordered_at']) ?></small></div></div><?= po_status_badge($po['status']) ?></li>
      <?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
  </div>
</div>
