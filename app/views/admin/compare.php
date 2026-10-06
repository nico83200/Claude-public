<div class="page-head">
  <div><h1>Comparateur fournisseurs</h1><p>Articles équivalents chez plusieurs fournisseurs (même code-barres ou même « groupe d'équivalence » renseigné sur la fiche article).</p></div>
  <form method="post" action="<?= url('admin/requests/optimize') ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= icon('sparkles', 18) ?> Basculer les demandes en attente vers le moins cher</button></form>
</div>
<div class="grid grid-3 mb-2">
  <div class="stat c-green"><div class="stat-icon g-green"><?= icon('euro', 24) ?></div><div><div class="stat-value"><?= money($potential) ?></div><div class="stat-label">Surcoût payé sur 12 mois vs le moins cher</div></div></div>
  <div class="stat c-indigo"><div class="stat-icon g-indigo"><?= icon('layers', 24) ?></div><div><div class="stat-value"><?= count($rows) ?></div><div class="stat-label">Groupes d'articles comparables</div></div></div>
  <div class="stat c-amber"><div class="stat-icon g-amber"><?= icon('tag', 24) ?></div><div><div class="stat-value"><?= $ungrouped ?></div><div class="stat-label">Articles sans groupe d'équivalence</div></div></div>
</div>
<?php if (!$rows): ?>
  <div class="card"><div class="empty"><?= icon('layers') ?><h3>Aucun article comparable</h3><p>Renseignez le même « groupe d'équivalence » (ex. <code>gants-nitrile-m</code>) sur les articles identiques de fournisseurs différents, depuis la fiche article ou l'import CSV (colonne <code>groupe_equivalence</code>).</p></div></div>
<?php endif; ?>
<div class="stack">
<?php foreach ($rows as $g): ?>
  <div class="card">
    <div class="card-head"><h3><?= icon('layers', 18) ?> <?= e($g['label']) ?></h3><?php $over = array_sum(array_column($g['items'], 'overspend')); if ($over > 0): ?><span class="badge badge-amber">Économie possible : <?= money($over) ?> / an</span><?php endif; ?></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Article</th><th>Fournisseur</th><th>Conditionnement</th><th class="num">Prix appliqué</th><th class="num">Écart</th><th class="num">Acheté (12 mois)</th></tr></thead>
      <tbody><?php foreach ($g['items'] as $i => $p): ?>
        <tr>
          <td><a href="<?= url('admin/product', ['id' => $p['id']]) ?>"><?= e($p['name']) ?></a><?= $i === 0 ? ' <span class="badge badge-green">Meilleur prix</span>' : '' ?></td>
          <td><span class="dot" style="background:<?= e($p['supplier_color']) ?>"></span> <?= e($p['supplier_name']) ?></td>
          <td><small><?= e($p['unit']) ?></small></td>
          <td class="num strong"><?= money($p['price']) ?></td>
          <td class="num"><?= $i ? '<span style="color:var(--red)">+' . money($p['price'] - $g['items'][0]['price']) . '</span>' : '—' ?></td>
          <td class="num"><?= (int)$p['qty12'] ?: '<small class="muted">—</small>' ?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
<?php endforeach; ?>
</div>
