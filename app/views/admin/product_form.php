<?php $p ??= []; $v = fn($k, $d = '') => e($p[$k] ?? $d); $fmt = fn($k) => isset($p[$k]) && $p[$k] !== null && $p[$k] !== '' ? e(number_format((float)$p[$k], 2, ',', '')) : ''; ?>
<div class="breadcrumb"><a href="<?= url('admin/products') ?>">Articles</a> <?= icon('chevron-right', 14) ?> <?= e($p['name'] ?? 'Nouveau') ?></div>
<div class="page-head"><h1><?= e($title) ?></h1>
  <?php if (!empty($p['id'])): ?><a class="btn" href="<?= url('labels', ['ids' => $p['id']]) ?>" target="_blank" rel="noopener"><?= icon('printer', 18) ?> Imprimer l'étiquette</a><?php endif; ?></div>
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
        <div class="field full"><label>Groupe d'équivalence (comparateur)</label><input type="text" name="compare_group" value="<?= $v('compare_group') ?>" placeholder="ex : gants-nitrile-m — même valeur pour le même produit chez d'autres fournisseurs" list="compare-groups">
          <datalist id="compare-groups"><?php foreach (all("SELECT DISTINCT compare_group FROM products WHERE compare_group IS NOT NULL AND compare_group <> '' ORDER BY compare_group") as $cg): ?><option value="<?= e($cg['compare_group']) ?>"><?php endforeach; ?></datalist>
          <small>Les articles d'un même groupe (ou de même code-barres) sont comparés et les demandes peuvent basculer vers le moins cher.</small></div>
        <div class="field full"><label>Mots-clés de recherche</label><input type="text" name="keywords" value="<?= $v('keywords') ?>" placeholder="synonymes, marques, usages : ex. latex free, examen, soins"><small>Aident le moteur de recherche à trouver l'article même avec d'autres mots.</small></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2><?= icon('euro') ?> Tarifs</h2></div>
      <div class="card-body form-grid">
        <div class="field"><label>Tarif catalogue HT (€)</label><input type="text" name="catalog_price" value="<?= $fmt('catalog_price') ?>" placeholder="0,00"></div>
        <?php if (!empty($contract)): ?>
        <div class="field"><label>Tarif négocié HT (€)</label><input type="text" value="<?= $fmt('negotiated_price') ?>" readonly><small>Prix fixé par le contrat <a href="<?= url('admin/contract', ['id' => $contract['id']]) ?>"><?= e($contract['name']) ?></a><?= $contract['buying_group'] ? ' (' . e($contract['buying_group']) . ')' : '' ?> jusqu'au <?= e(date_fr($contract['end_date'])) ?> : modifiable depuis le contrat.</small></div>
        <?php else: ?>
        <div class="field"><label>Tarif négocié HT (€)</label><input type="text" name="negotiated_price" value="<?= $fmt('negotiated_price') ?>" placeholder="laisser vide si aucun"><small>Prix appliqué aux demandes et bons de commande s'il est renseigné.</small></div>
        <?php endif; ?>
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
<?php if (!empty($p['id'])): $hist = all('SELECT h.*, u.first_name, u.last_name FROM price_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.product_id = ? ORDER BY h.created_at DESC, h.id DESC LIMIT 20', [$p['id']]); $eqs = product_equivalents($p); ?>
<div class="grid grid-2 mt-3">
  <div class="card">
    <div class="card-head"><h3><?= icon('chart', 18) ?> Historique des prix</h3></div>
    <?php if ($hist): ?><div class="table-wrap"><table class="table">
      <thead><tr><th>Date</th><th class="num">Catalogue</th><th class="num">Négocié</th><th>Origine</th></tr></thead>
      <tbody><?php foreach ($hist as $i => $h): $prev = $hist[$i + 1] ?? null; $eff = fn($r) => $r['negotiated_price'] !== null && (float)$r['negotiated_price'] > 0 ? (float)$r['negotiated_price'] : (float)$r['catalog_price']; $up = $prev && $eff($h) > $eff($prev) + 0.004; $down = $prev && $eff($h) < $eff($prev) - 0.004; ?>
        <tr><td class="nowrap"><small><?= date_fr($h['created_at'], true) ?></small></td><td class="num"><?= money($h['catalog_price']) ?></td>
          <td class="num strong" style="color:<?= $up ? 'var(--red)' : ($down ? 'var(--green)' : 'inherit') ?>"><?= $h['negotiated_price'] !== null ? money($h['negotiated_price']) : '—' ?> <?= $up ? '▲' : ($down ? '▼' : '') ?></td>
          <td><small><?= e($h['source']) ?><?= $h['first_name'] ? ' · ' . e($h['first_name']) : '' ?></small></td></tr>
      <?php endforeach; ?></tbody>
    </table></div><?php else: ?><div class="empty"><p>Pas encore d'historique.</p></div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('layers', 18) ?> Équivalents chez d'autres fournisseurs</h3></div>
    <?php if ($eqs): ?><ul class="list"><?php foreach ($eqs as $q): ?>
      <li><span class="dot" style="background:<?= e($q['supplier_color']) ?>"></span><div class="grow"><a class="title" href="<?= url('admin/product', ['id' => $q['id']]) ?>"><?= e($q['name']) ?></a><small><?= e($q['supplier_name']) ?> · <?= e($q['unit']) ?></small></div>
        <strong style="color:<?= effective_price($q) < effective_price($p) ? 'var(--green)' : 'inherit' ?>"><?= money(effective_price($q)) ?></strong></li>
    <?php endforeach; ?></ul><?php else: ?><div class="card-body"><small class="muted">Aucun équivalent : renseignez un groupe d'équivalence pour comparer.</small></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php if (!empty($p['id'])): ?>
<form method="post" action="<?= url('admin/products/delete') ?>" class="card card-body mt-2 danger-zone">
  <?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int)$p['id'] ?>">
  <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div><strong>Supprimer cet article</strong><br><small class="muted"><?= $usedInOrders
        ? 'Il figure dans ' . (int)$usedInOrders . ' ligne(s) de demandes ou de bons de commande : il sera masqué du catalogue (et retiré des paniers et listes types), l\'historique restant intact.'
        : 'Il n\'a jamais été commandé : il sera effacé définitivement, avec son stock et son historique de prix.' ?></small></div>
    <button class="btn btn-danger" type="submit" data-confirm="<?= $usedInOrders ? 'Masquer' : 'Supprimer définitivement' ?> l'article « <?= e($p['name']) ?> » ?"><?= icon('trash', 18) ?> <?= $usedInOrders ? 'Masquer l\'article' : 'Supprimer l\'article' ?></button>
  </div>
</form>
<?php endif; ?>
