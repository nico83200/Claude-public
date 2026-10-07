<div class="page-head">
  <div><h1>Articles</h1><p><?= plural(count($products), 'article', 'articles') ?> · base commune à tous les centres</p></div>
  <div class="row row-wrap">
    <a class="btn" href="<?= url('admin/products/export') ?>"><?= icon('download', 18) ?> Exporter</a>
    <a class="btn" href="<?= url('admin/products/import') ?>"><?= icon('upload', 18) ?> Importer (CSV, Excel)</a>
    <a class="btn btn-primary" href="<?= url('admin/product', ['supplier_id' => $sup]) ?>"><?= icon('plus', 18) ?> Nouvel article</a>
  </div>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="admin/products">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher…" style="min-width:240px">
  <select name="sup"><option value="">Tous les fournisseurs</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= $sup === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  <select name="cat"><option value="">Toutes les catégories</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="state">
    <?php foreach (['active' => 'Actifs', 'inactive' => 'Inactifs', 'nophoto' => 'Sans photo', 'all' => 'Tous'] as $k => $l): ?><option value="<?= $k ?>" <?= $state === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
  </select>
  <button class="btn" type="submit"><?= icon('filter', 16) ?> Filtrer</button>
</form>
<div class="card">
  <?php if ($products): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th></th><th>Article</th><th>Fournisseur</th><th>Catégorie</th><th class="num">Tarif catalogue</th><th class="num">Tarif négocié</th><th class="num">Remise</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): $neg = $p['negotiated_price'] !== null && (float)$p['negotiated_price'] > 0; ?>
      <tr class="<?= $p['active'] ? '' : 'is-done' ?>">
        <td style="width:52px"><?php partial('thumb', ['p' => $p, 'size' => 40]); ?></td>
        <td><a class="strong" href="<?= url('admin/product', ['id' => $p['id']]) ?>"><?= e($p['name']) ?></a><div><small><?= $p['reference'] ? 'Réf. ' . e($p['reference']) . ' · ' : '' ?><?= e($p['unit']) ?></small></div></td>
        <td><span class="dot" style="background:<?= e($p['supplier_color']) ?>"></span> <?= e($p['supplier_name']) ?></td>
        <td><?= $p['category_name'] ? '<span class="badge" style="background:' . e($p['category_color']) . '22;color:' . e($p['category_color']) . '">' . e($p['category_name']) . '</span>' : '<small class="muted">—</small>' ?></td>
        <td class="num"><?= money($p['catalog_price']) ?></td>
        <td class="num strong"><?= $neg ? money($p['negotiated_price']) : '<small class="muted">—</small>' ?></td>
        <td class="num"><?= $neg && (float)$p['catalog_price'] > 0 && (float)$p['negotiated_price'] < (float)$p['catalog_price'] ? '<span class="badge badge-green">-' . round(100 - $p['negotiated_price'] / $p['catalog_price'] * 100) . ' %</span>' : '' ?></td>
        <td class="nowrap text-right">
          <a class="btn btn-ghost btn-sm btn-icon" href="<?= url('admin/product', ['id' => $p['id']]) ?>" title="Modifier"><?= icon('edit', 16) ?></a>
          <a class="btn btn-ghost btn-sm btn-icon" href="<?= url('admin/product', ['copy' => $p['id']]) ?>" title="Dupliquer"><?= icon('repeat', 16) ?></a>
          <form method="post" action="<?= url('admin/product/toggle', ['id' => $p['id']]) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-icon" type="submit" title="<?= $p['active'] ? 'Masquer' : 'Réactiver' ?>"><?= icon($p['active'] ? 'eye' : 'check', 16) ?></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><div class="empty"><?= icon('box') ?><p>Aucun article.</p></div><?php endif; ?>
</div>
