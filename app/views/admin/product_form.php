<?php $p ??= []; $v = fn($k, $d = '') => e($p[$k] ?? $d); $fmt = fn($k) => isset($p[$k]) && $p[$k] !== null && $p[$k] !== '' ? e(number_format((float)$p[$k], 2, ',', '')) : ''; ?>
<div class="breadcrumb"><a href="<?= url('admin/products') ?>">Articles</a> <?= icon('chevron-right', 14) ?> <?= e($p['name'] ?? 'Nouveau') ?></div>
<h1 class="mb-2"><?= e($title) ?></h1>
<form method="post" enctype="multipart/form-data" class="grid grid-main">
  <?= csrf_field() ?>
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('box') ?> Description</h2></div>
      <div class="card-body form-grid">
        <div class="field full"><label>Désignation *</label><input type="text" name="name" value="<?= $v('name') ?>" required placeholder="ex : Gants d'examen nitrile non poudrés — taille M"></div>
        <div class="field"><label>Fournisseur *</label>
          <select name="supplier_id" required><option value="">—</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int)($p['supplier_id'] ?? $defaultSupplier) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Catégorie</label>
          <select name="category_id"><option value="">—</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($p['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Référence fournisseur</label><input type="text" name="reference" value="<?= $v('reference') ?>"></div>
        <div class="field"><label>Code-barres (EAN / GTIN)</label>
          <div class="input-group"><input type="text" name="barcode" id="barcode" value="<?= $v('barcode') ?>" inputmode="numeric" placeholder="Scanner ou saisir"><button class="btn" type="button" data-scan="fill:#barcode" title="Scanner avec la caméra"><?= icon('camera', 18) ?></button></div>
        </div>
        <div class="field"><label>Conditionnement</label><input type="text" name="unit" value="<?= $v('unit') ?>" placeholder="ex : boîte de 100"></div>
        <div class="field full"><label>Descriptif</label><textarea name="description" rows="4"><?= $v('description') ?></textarea></div>
        <div class="field full"><label>Mots-clés de recherche</label><input type="text" name="keywords" value="<?= $v('keywords') ?>" placeholder="synonymes, marques, usages : ex. latex free, examen, soins"><small>Aident le moteur de recherche à trouver l'article même avec d'autres mots.</small></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2><?= icon('euro') ?> Tarifs</h2></div>
      <div class="card-body form-grid">
        <div class="field"><label>Tarif catalogue HT (€)</label><input type="text" name="catalog_price" value="<?= $fmt('catalog_price') ?>" placeholder="0,00"></div>
        <div class="field"><label>Tarif négocié HT (€)</label><input type="text" name="negotiated_price" value="<?= $fmt('negotiated_price') ?>" placeholder="laisser vide si aucun"><small>Prix appliqué aux demandes et bons de commande s'il est renseigné.</small></div>
        <div class="field"><label>TVA (%)</label><input type="text" name="vat_rate" value="<?= e(number_format((float)($p['vat_rate'] ?? 20), 2, ',', '')) ?>"></div>
        <div class="field"><label>Quantité proposée par défaut</label><input type="number" name="min_qty" min="1" value="<?= (int)($p['min_qty'] ?? 1) ?>"></div>
      </div>
    </div>
  </div>
  <div class="stack">
    <div class="card card-body">
      <label>Photo (optionnelle)</label>
      <?php if (!empty($p['image'])): ?>
        <img src="<?= e(product_image_url($p['image'])) ?>" alt="" style="border-radius:12px;border:1px solid var(--border);max-height:220px;object-fit:contain;width:100%;background:#fff">
        <label class="check mt-1"><input type="checkbox" name="remove_image" value="1"> Supprimer la photo</label>
      <?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#img-preview">
      <img id="img-preview" class="hidden mt-1" alt="" style="border-radius:12px;max-height:200px;object-fit:contain;width:100%">
      <small class="muted">JPG, PNG ou WEBP, 4 Mo max.</small>
    </div>
    <div class="card card-body">
      <label class="check"><input type="checkbox" name="active" value="1" <?= ($p['active'] ?? 1) ? 'checked' : '' ?>> Visible dans le catalogue</label>
      <button class="btn btn-primary btn-lg mt-1" type="submit"><?= icon('check', 18) ?> Enregistrer</button>
      <button class="btn mt-1" type="submit" name="then" value="new"><?= icon('plus', 16) ?> Enregistrer et créer un autre</button>
    </div>
  </div>
</form>
