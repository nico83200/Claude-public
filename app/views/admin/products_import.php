<div class="breadcrumb"><a href="<?= url('admin/products') ?>">Articles</a> <?= icon('chevron-right', 14) ?> Import</div>
<h1>Importer des articles</h1>
<p class="muted">Idéal pour charger le catalogue d'un fournisseur. Un article existant (même fournisseur + même référence) est mis à jour.</p>
<?php if ($report): ?>
  <div class="flash flash-success"><?= icon('check-circle') ?><div><?= $report['created'] ?> article(s) créé(s), <?= $report['updated'] ?> mis à jour.</div></div>
  <?php foreach (array_slice($report['errors'], 0, 30) as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
<?php endif; ?>
<div class="grid grid-2 mt-2">
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <div class="card-head"><h2><?= icon('upload') ?> Fichier CSV</h2></div>
    <div class="card-body">
      <div class="field"><label>Fichier (séparateur « ; » ou « , », encodage UTF-8)</label><input type="file" name="csv" accept=".csv,text/csv" required></div>
      <div class="field"><label>Fournisseur par défaut (si la colonne « fournisseur » est vide)</label>
        <select name="supplier_id"><option value="">—</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('upload', 18) ?> Importer</button>
    </div>
  </form>
  <div class="card">
    <div class="card-head"><h2><?= icon('info') ?> Format attendu</h2></div>
    <div class="card-body">
      <p>Première ligne = en-têtes. Colonnes reconnues :</p>
      <div class="chips mb-2"><?php foreach (IMPORT_COLUMNS as $c): ?><code class="chip"><?= e($c) ?></code><?php endforeach; ?></div>
      <p class="muted" style="font-size:.85rem">Seule « designation » est obligatoire. Les fournisseurs et catégories inconnus sont créés automatiquement. Astuce : exportez le catalogue actuel pour obtenir un modèle.</p>
      <a class="btn btn-sm" href="<?= url('admin/products/export') ?>"><?= icon('download', 15) ?> Télécharger un modèle (export)</a>
    </div>
  </div>
</div>
