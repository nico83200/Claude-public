<div class="page-head"><div><h1>Stocks des centres</h1><p>Vue consolidée des inventaires. Ouvrez un centre pour le détail.</p></div></div>
<div class="grid grid-3 mb-2">
<?php foreach ($rows as $r): ?>
  <div class="card" style="border-top:5px solid <?= e($r['color']) ?>">
    <div class="card-body">
      <h3><?= e($r['name']) ?></h3>
      <div class="chips mt-1">
        <span class="badge badge-violet"><?= (int)$r['nb'] ?> articles suivis</span>
        <span class="badge <?= $r['low'] ? 'badge-red' : 'badge-green' ?>"><?= (int)$r['low'] ?> stock(s) bas</span>
        <span class="badge badge-gray">Valeur <?= money($r['value']) ?></span>
      </div>
      <small class="muted mt-1" style="display:block">Dernier inventaire : <?= $r['last_count'] ? date_fr($r['last_count']) : 'jamais' ?></small>
    </div>
    <div class="card-foot"><a class="btn btn-sm" href="<?= url('stock', ['c' => $r['id']]) ?>"><?= icon('layers', 15) ?> Ouvrir l'inventaire</a></div>
  </div>
<?php endforeach; ?>
</div>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2><?= icon('alert') ?> Articles sous le seuil d'alerte</h2></div>
    <?php if ($low): ?><div class="table-wrap"><table class="table">
      <thead><tr><th>Centre</th><th>Article</th><th class="num">Stock</th><th class="num">Seuil</th></tr></thead>
      <tbody><?php foreach ($low as $l): ?><tr><td><span class="dot" style="background:<?= e($l['center_color']) ?>"></span> <?= e($l['center_name']) ?></td><td><?= e($l['name']) ?><div><small><?= e($l['supplier_name']) ?></small></div></td><td class="num strong" style="color:var(--red)"><?= (int)$l['qty'] ?></td><td class="num"><?= (int)$l['alert_qty'] ?></td></tr><?php endforeach; ?></tbody>
    </table></div><?php else: ?><div class="empty"><?= icon('check-circle') ?><p>Aucun stock bas.</p></div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('activity') ?> Plus fortes consommations (90 jours)</h2></div>
    <?php if ($exits): $max = max(1, ...array_map(fn($x) => (int)$x['qty'], $exits)); ?><div class="card-body">
      <?php foreach ($exits as $x): ?><div class="hbar"><div class="hbar-head"><span><?= e($x['name']) ?></span><strong><?= (int)$x['qty'] ?></strong></div><div class="progress"><span style="width:<?= (int)$x['qty'] / $max * 100 ?>%"></span></div></div><?php endforeach; ?>
    </div><?php else: ?><div class="empty"><p>Pas encore de sorties déclarées.</p></div><?php endif; ?>
  </div>
</div>
