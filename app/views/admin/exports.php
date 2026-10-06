<div class="page-head"><div><h1>Exports comptables</h1><p>Fichiers CSV (séparateur « ; », compatibles Excel et logiciels comptables) basés sur les bons commandés sur la période.</p></div></div>
<form method="get" action="index.php" class="card" style="max-width:860px">
  <input type="hidden" name="r" value="admin/exports/download">
  <div class="card-body form-grid">
    <div class="field"><label>Du</label><input type="date" name="from" value="<?= date('Y-01-01') ?>"></div>
    <div class="field"><label>Au</label><input type="date" name="to" value="<?= date('Y-m-d') ?>"></div>
    <div class="field"><label>Centre</label><select name="center"><option value="">Tous</option><?php foreach ($centers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Fournisseur</label><select name="supplier"><option value="">Tous</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field full"><label>Type d'export</label>
      <?php foreach (['lines' => ['Détail des lignes', 'une ligne par article commandé : centre, fournisseur, catégorie, quantités, HT, TVA, TTC, n° de facture'],
                      'center' => ['Dépenses par centre', 'total HT / TTC et économies négociées'], 'supplier' => ['Dépenses par fournisseur', ''],
                      'category' => ['Dépenses par catégorie', ''], 'month' => ['Dépenses par mois', ''], 'center_month' => ['Par centre et par mois', 'pour l\'imputation analytique'],
                      'invoices' => ['Rapprochement des factures', 'commandé, reçu, facturé et écarts par bon']] as $k => [$l, $d]): ?>
        <label class="check"><input type="radio" name="type" value="<?= $k ?>" <?= $k === 'lines' ? 'checked' : '' ?>> <strong><?= $l ?></strong> <?php if ($d): ?><small class="muted">— <?= e($d) ?></small><?php endif; ?></label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('download', 18) ?> Télécharger le fichier</button></div>
</form>
