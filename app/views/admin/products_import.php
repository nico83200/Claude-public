<div class="breadcrumb"><a href="<?= url('admin/products') ?>">Articles</a> <?= icon('chevron-right', 14) ?> Import</div>
<h1>Importer des articles</h1>
<p class="muted">Déposez le catalogue ou le tarif d'un fournisseur tel quel : l'assistant reconnaît les colonnes, rapproche fournisseurs et catégories, repère les articles déjà présents. Vous vérifiez tout avant l'import.</p>
<div class="import-steps"><span class="active">1. Fichier</span><span>2. Colonnes</span><span>3. Vérification et import</span></div>
<div class="grid grid-2 mt-2">
  <form method="post" enctype="multipart/form-data" class="card" data-busy="Lecture du fichier et analyse des colonnes…">
    <?= csrf_field() ?><input type="hidden" name="step" value="upload">
    <div class="card-head"><h2><?= icon('upload') ?> Fichier à importer</h2></div>
    <div class="card-body">
      <div class="field"><label>Fichier CSV, Excel (.xlsx, .xls exporté d'un site) ou OpenDocument (.ods)</label>
        <input type="file" name="file" accept=".csv,.txt,.xlsx,.xls,.ods,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/vnd.oasis.opendocument.spreadsheet" required></div>
      <div class="field"><label>Fournisseur (si le fichier n'a pas de colonne « fournisseur »)</label>
        <select name="supplier_id"><option value="">— Choisir —</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select>
        <small class="muted">Vous pourrez aussi le choisir à l'étape suivante.</small>
      </div>
      <label class="check"><input type="checkbox" name="use_ai" value="1" <?= $aiReady ? 'checked' : 'disabled' ?>> <span><?= icon('sparkles', 15) ?> Correspondances par l'IA <small class="muted">(colonnes, fournisseurs, catégories)</small></span></label>
      <?php if (!$aiReady): ?><small class="muted">Assistant IA non configuré (<a href="<?= url('admin/settings') ?>">Paramètres</a>) : la reconnaissance des colonnes se fera par leurs intitulés.</small><?php endif; ?>
      <button class="btn btn-primary mt-1" type="submit"><?= icon('upload', 18) ?> Analyser le fichier</button>
    </div>
  </form>
  <div class="card">
    <div class="card-head"><h2><?= icon('info') ?> Comment ça marche</h2></div>
    <div class="card-body">
      <ol class="import-help">
        <li><strong>Aucun modèle imposé</strong> : les intitulés de colonnes, les lignes de titre en haut du fichier, les prix « 12,50 € » ou TTC sont gérés.</li>
        <li><strong>L'IA propose la correspondance</strong> des colonnes (désignation, référence, EAN, conditionnement, prix catalogue / négocié, TVA…) : vous la corrigez si besoin.</li>
        <li>Elle <strong>rapproche les fournisseurs et les familles</strong> du fichier de vos catégories, et classe les articles qui n'en ont pas.</li>
        <li>Les articles déjà au catalogue (même référence, même code-barres ou même désignation chez ce fournisseur) sont <strong>mis à jour</strong> plutôt que dupliqués, avec historique des prix.</li>
      </ol>
      <p class="muted" style="font-size:.85rem">Un ancien fichier Excel 97-2003 (.xls binaire) doit d'abord être enregistré en .xlsx ou en CSV. Jusqu'à 5 000 lignes par import.</p>
      <a class="btn btn-sm" href="<?= url('admin/products/export') ?>"><?= icon('download', 15) ?> Exporter le catalogue actuel (CSV)</a>
    </div>
  </div>
</div>
