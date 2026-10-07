<?php
$supOptions = fn(int $sel, bool $allowNew) => ($allowNew ? '<option value="0">+ Créer ce fournisseur</option>' : '')
    . implode('', array_map(fn($s) => '<option value="' . $s['id'] . '"' . ((int)$s['id'] === $sel ? ' selected' : '') . '>' . e($s['name']) . '</option>', $suppliers));
$catOptions = fn(int $sel, string $empty) => '<option value="0">' . e($empty) . '</option>'
    . implode('', array_map(fn($c) => '<option value="' . $c['id'] . '"' . ((int)$c['id'] === $sel ? ' selected' : '') . '>' . e($c['name']) . '</option>', $categories));
$statusBadge = ['new' => '<span class="badge badge-green">Nouveau</span>', 'update' => '<span class="badge badge-blue">Mise à jour</span>', 'error' => '<span class="badge badge-red">Ignoré</span>'];
?>
<div class="breadcrumb"><a href="<?= url('admin/products') ?>">Articles</a> <?= icon('chevron-right', 14) ?> <a href="<?= url('admin/products/import') ?>">Import</a> <?= icon('chevron-right', 14) ?> Vérification</div>
<h1>Vérification avant import</h1>
<p class="muted"><?= e($state['file']) ?></p>
<div class="import-steps"><span class="done">1. Fichier</span><span class="done"><a href="<?= url('admin/products/import', ['token' => $state['token'], 'step' => 'map']) ?>">2. Colonnes</a></span><span class="active">3. Vérification et import</span></div>
<?php if ($state['ai_error']): ?>
  <div class="flash flash-error mt-2"><?= icon('alert') ?><div>Rapprochement par l'IA incomplet : <?= e($state['ai_error']) ?></div></div>
<?php endif; ?>
<div class="grid grid-3 mt-2 mb-2">
  <div class="stat c-green"><div class="stat-icon g-green"><?= icon('plus', 22) ?></div><div><div class="stat-value"><?= (int)$counts['new'] ?></div><div class="stat-label">nouveaux articles</div></div></div>
  <div class="stat c-blue"><div class="stat-icon g-blue"><?= icon('refresh', 22) ?></div><div><div class="stat-value"><?= (int)$counts['update'] ?></div><div class="stat-label">déjà au catalogue (mise à jour)</div></div></div>
  <div class="stat c-pink"><div class="stat-icon g-pink"><?= icon('alert', 22) ?></div><div><div class="stat-value"><?= (int)$counts['error'] ?></div><div class="stat-label">lignes ignorées</div></div></div>
</div>
<?php $limit = price_alert_pct(); $hikes = array_filter($rows, fn($r) => $r['status'] === 'update' && $r['price_pct'] !== null && $r['price_pct'] > $limit); ?>
<?php if ($hikes): $maxHike = max(array_column($hikes, 'price_pct')); ?>
  <div class="flash flash-error"><?= icon('alert') ?><div><strong><?= plural(count($hikes), 'hausse de prix', 'hausses de prix') ?> de plus de <?= e((string)$limit) ?> %</strong> dans ce fichier (jusqu'à +<?= e((string)$maxHike) ?> %). Elles sont signalées en rouge ci-dessous.
    <button class="btn btn-sm mt-1" type="button" data-uncheck-hikes>Ne pas importer ces hausses</button></div></div>
<?php endif; ?>
<form method="post" id="import-form" data-busy="Import en cours…">
  <?= csrf_field() ?><input type="hidden" name="step" value="preview"><input type="hidden" name="token" value="<?= e($state['token']) ?>">
  <?php if ($state['supplier_values'] || $state['category_values'] || !isset($state['mapping']['supplier'])): ?>
  <div class="grid grid-2 mb-2">
    <div class="card">
      <div class="card-head"><h2><?= icon('truck') ?> Fournisseurs</h2></div>
      <div class="card-body">
        <?php if (!$state['supplier_values']): ?>
          <div class="field mb-0"><label>Fournisseur de tous les articles</label><select name="supplier_id"><option value="0">— Choisir —</option><?= $supOptions((int)$state['default_supplier'], false) ?></select></div>
        <?php else: ?>
          <?php foreach ($state['supplier_values'] as $k => $v): ?>
            <div class="map-row"><span class="map-from"><?= e($v) ?></span><?= icon('chevron-right', 14) ?><select name="sup[<?= $k ?>]"><?= $supOptions((int)$state['supplier_map'][$v], true) ?></select></div>
          <?php endforeach; ?>
          <div class="field mt-1 mb-0"><label>Lignes sans fournisseur</label><select name="supplier_id"><option value="0">— Ignorer —</option><?= $supOptions((int)$state['default_supplier'], false) ?></select></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2><?= icon('tag') ?> Catégories du fichier</h2></div>
      <div class="card-body">
        <?php if (!$state['category_values']): ?>
          <p class="muted mb-0" style="font-size:.9rem">Pas de colonne « catégorie » : <?= $state['row_categories'] ? 'l\'IA a proposé une catégorie pour chaque article (colonne de droite, modifiable).' : 'choisissez la catégorie article par article si besoin.' ?></p>
        <?php else: ?>
          <?php foreach ($state['category_values'] as $k => $v): ?>
            <div class="map-row"><span class="map-from"><?= e($v) ?></span><?= icon('chevron-right', 14) ?><select name="cat[<?= $k ?>]"><?= $catOptions((int)$state['category_map'][$v], 'Créer « ' . mb_substr($v, 0, 30) . ' » / IA') ?></select></div>
          <?php endforeach; ?>
          <label class="check mt-1" style="font-size:.88rem"><input type="checkbox" name="create_categories" value="1" <?= !empty($state['create_categories']) ? 'checked' : '' ?>> Créer les catégories non rapprochées (sinon, la proposition de l'IA ou aucune catégorie)</label>
        <?php endif; ?>
        <button class="btn btn-sm mt-1" type="submit" name="do" value="refresh"><?= icon('refresh', 15) ?> Appliquer ces choix à l'aperçu</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head"><h2><?= icon('box') ?> Articles (<?= count($rows) ?>)</h2>
      <div class="row" style="gap:1rem"><label class="mb-0" style="font-size:.85rem;font-weight:500">Signaler les hausses au-delà de <input type="number" name="price_alert_pct" value="<?= e((string)$limit) ?>" min="0" max="100" step="0.5" style="width:70px;display:inline-block"> %</label>
      <label class="check mb-0" style="font-size:.88rem"><input type="checkbox" name="update_existing" value="1" checked> Mettre à jour les articles déjà au catalogue</label></div></div>
    <div class="table-wrap import-preview"><table class="table">
      <thead><tr><th class="col-check"><input type="checkbox" data-check-all="rows[]" checked aria-label="Tout sélectionner"></th><th>Statut</th><th>Désignation</th><th>Réf. / EAN</th><th>Cond.</th><th class="text-right">Catalogue HT</th><th class="text-right">Négocié HT</th><th class="text-right">Variation</th><th>TVA</th><th>Fournisseur</th><th>Catégorie</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['status'] === 'error' ? 'row-muted' : '' ?>">
          <?php $hike = $r['status'] === 'update' && $r['price_pct'] !== null && $r['price_pct'] > $limit; ?>
          <td class="col-check"><?php if ($r['status'] !== 'error'): ?><input type="checkbox" name="rows[]" value="<?= (int)$r['line'] ?>" checked <?= $hike ? 'data-hike' : '' ?>><?php endif; ?></td>
          <td><?= $statusBadge[$r['status']] ?><?php if ($r['error']): ?><br><small class="text-red"><?= e($r['error']) ?></small><?php endif; ?></td>
          <td><div class="strong"><?= e($r['name']) ?></div><?php if ($r['description']): ?><small class="muted"><?= e(mb_substr($r['description'], 0, 80)) ?></small><?php endif; ?></td>
          <td><small><?= e($r['reference'] ?? '') ?><?= $r['barcode'] ? '<br>' . e($r['barcode']) : '' ?></small></td>
          <td><small><?= e($r['unit'] ?? '') ?></small></td>
          <td class="text-right nowrap"><?= $r['catalog_price'] !== null ? money($r['catalog_price']) : '—' ?></td>
          <td class="text-right nowrap"><?= $r['negotiated_price'] !== null ? money($r['negotiated_price']) : '' ?></td>
          <td class="text-right nowrap"><?php if ($r['price_pct'] !== null && abs($r['price_pct']) >= 0.1): ?><span class="badge badge-<?= $hike ? 'red' : ($r['price_pct'] > 0 ? 'amber' : 'green') ?>" title="Prix actuel : <?= money($r['old_price']) ?>"><?= $r['price_pct'] > 0 ? '+' : '' ?><?= e(str_replace('.', ',', (string)$r['price_pct'])) ?> %</span><?php elseif ($r['status'] === 'update'): ?><small class="muted">=</small><?php endif; ?></td>
          <td class="nowrap"><small><?= rtrim(rtrim(number_format($r['vat_rate'], 2, ',', ''), '0'), ',') ?> %</small></td>
          <td><small><?= e($r['supplier_name']) ?></small></td>
          <td><?php if ($r['status'] !== 'error'): ?><select name="rowcat[<?= (int)$r['line'] ?>]" class="select-sm"><?= $catOptions((int)$r['category_id'], $r['category_text'] !== '' ? $r['category_text'] . ' (fichier)' : '— Aucune —') ?></select><?php if ($r['category_ai']): ?> <span title="Proposée par l'IA"><?= icon('sparkles', 13) ?></span><?php endif; ?><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="card-body row" style="justify-content:space-between;flex-wrap:wrap">
      <a class="btn btn-ghost" href="<?= url('admin/products/import', ['token' => $state['token'], 'step' => 'map']) ?>">← Revoir les colonnes</a>
      <button class="btn btn-primary btn-lg" type="submit" name="do" value="import" data-confirm="Importer les articles cochés dans le catalogue ?"><?= icon('check', 18) ?> Importer les articles cochés</button>
    </div>
  </div>
</form>
