<?php $m = $state['mapping']; ?>
<div class="breadcrumb"><a href="<?= url('admin/products') ?>">Articles</a> <?= icon('chevron-right', 14) ?> <a href="<?= url('admin/products/import') ?>">Import</a> <?= icon('chevron-right', 14) ?> Colonnes</div>
<h1>Correspondance des colonnes</h1>
<p class="muted"><?= e($state['file']) ?> · <?= count($state['rows']) ?> ligne(s) de données<?= $state['skipped_top'] ? ' · ' . (int)$state['skipped_top'] . ' ligne(s) de titre ignorée(s) en haut du fichier' : '' ?><?= $state['truncated'] ? ' · limité aux 5 000 premières lignes' : '' ?></p>
<div class="import-steps"><span class="done">1. Fichier</span><span class="active">2. Colonnes</span><span>3. Vérification et import</span></div>
<?php if ($state['mapping_source'] === 'ai'): ?>
  <div class="flash flash-info mt-2"><?= icon('sparkles') ?><div>Correspondance proposée par l'IA. Vérifiez-la, puis continuez.<?= $state['mapping_notes'] ? '<br><small>' . e($state['mapping_notes']) . '</small>' : '' ?></div></div>
<?php elseif ($state['ai_error']): ?>
  <div class="flash flash-error mt-2"><?= icon('alert') ?><div>L'IA n'a pas pu analyser le fichier : <?= e($state['ai_error']) ?><br><small>Correspondance établie d'après les intitulés des colonnes.</small></div></div>
<?php endif; ?>
<form method="post" class="card mt-2" data-busy="Rapprochement des fournisseurs et des catégories…">
  <?= csrf_field() ?><input type="hidden" name="step" value="map"><input type="hidden" name="token" value="<?= e($state['token']) ?>">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Champ du catalogue</th><th>Colonne du fichier</th><th>Exemples de valeurs</th></tr></thead>
    <tbody>
    <?php foreach (IMPORT_FIELDS as $field => [$label]): ?>
      <tr>
        <td class="strong nowrap"><?= e($label) ?></td>
        <td><select name="map[<?= $field ?>]" data-map-select style="min-width:220px">
          <option value="">— Ne pas importer —</option>
          <?php foreach ($state['headers'] as $i => $h): ?><option value="<?= $i ?>" <?= ($m[$field] ?? null) === $i ? 'selected' : '' ?>><?= e(mb_substr($h, 0, 50)) ?></option><?php endforeach; ?>
        </select></td>
        <td><small class="muted" data-samples><?= isset($m[$field]) ? e(implode(' · ', array_map(fn($v) => mb_substr($v, 0, 40), array_slice($samples[$m[$field]] ?? [], 0, 3)))) : '' ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="card-body">
    <div class="grid grid-2">
      <div class="field"><label>Fournisseur par défaut (lignes sans fournisseur)</label>
        <select name="supplier_id"><option value="">— Aucun —</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int)$state['default_supplier'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>&nbsp;</label><label class="check"><input type="checkbox" name="prices_ttc" value="1" <?= $state['prices_ttc'] ? 'checked' : '' ?>> Les prix du fichier sont TTC (convertis en HT selon la TVA)</label></div>
    </div>
    <div class="row"><a class="btn btn-ghost" href="<?= url('admin/products/import') ?>">Changer de fichier</a>
    <button class="btn btn-primary" type="submit"><?= !empty($state['use_ai']) ? icon('sparkles', 18) : icon('check', 18) ?> Continuer<?= !empty($state['use_ai']) ? ' : rapprochement par l\'IA' : '' ?></button></div>
  </div>
</form>
<script>
  // Exemples de valeurs mis à jour quand on change de colonne
  window.IMPORT_SAMPLES = <?= json_encode(array_map(fn($v) => implode(' · ', array_map(fn($x) => mb_substr($x, 0, 40), array_slice($v, 0, 3))), $samples), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
