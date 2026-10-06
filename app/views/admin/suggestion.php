<?php $pending = $s['status'] === 'pending'; ?>
<div class="breadcrumb"><a href="<?= url('admin/suggestions') ?>">Articles proposés</a> <?= icon('chevron-right', 14) ?> <?= e($s['name']) ?></div>
<div class="page-head">
  <div>
    <h1><?= e($s['name']) ?> <?= badge(SUGGESTION_STATUSES[$s['status']]) ?></h1>
    <p>Proposé par <strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong><?= $s['job'] ? ' (' . e($s['job']) . ')' : '' ?> · <?= e($s['center_name']) ?> · <?= date_fr($s['created_at'], true) ?>
      · <?= $s['source'] === 'scan' ? 'code-barres scanné inconnu' : 'depuis le panier' ?></p>
  </div>
</div>

<div class="grid grid-main">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('users') ?> Ce qu'a indiqué le salarié</h2><?php if ($s['qty']): ?><span class="badge badge-pink">Demande de <?= (int)$s['qty'] ?> unité(s)<?= $s['urgent'] ? ' · urgent' : '' ?></span><?php endif; ?></div>
      <div class="card-body">
        <div class="row" style="align-items:flex-start;gap:1.25rem">
          <?php if ($s['image']): ?><a href="<?= e(product_image_url($s['image'])) ?>" target="_blank"><img src="<?= e(product_image_url($s['image'])) ?>" alt="" style="width:180px;border-radius:12px;border:1px solid var(--border);background:#fff"></a><?php endif; ?>
          <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:.35rem 1rem;font-size:.92rem">
            <?php foreach (['brand' => 'Marque', 'reference' => 'Référence', 'unit' => 'Conditionnement', 'barcode' => 'Code-barres', 'supplier_hint' => 'Fournisseur indiqué', 'estimated_price' => 'Prix constaté', 'description' => 'Précisions'] as $k => $lbl): if (!$s[$k]) continue; ?>
              <dt class="muted"><?= $lbl ?></dt><dd style="margin:0"><?= $k === 'estimated_price' ? money($s[$k]) : nl2br(e($s[$k])) ?></dd>
            <?php endforeach; ?>
            <?php if ($s['url']): ?><dt class="muted">Lien</dt><dd style="margin:0;word-break:break-all"><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($s['url']) ?></a></dd><?php endif; ?>
            <?php if ($s['request_comment']): ?><dt class="muted">Commentaire de la demande</dt><dd style="margin:0"><?= e($s['request_comment']) ?></dd><?php endif; ?>
          </dl>
        </div>
        <?php if (!$pending && $s['admin_note']): ?><div class="flash flash-info mt-2 mb-0"><?= icon('info') ?><div><?= e($s['admin_note']) ?></div></div><?php endif; ?>
        <?php if (!$pending && $s['product_id']): ?><a class="btn btn-sm mt-2" href="<?= url('admin/product', ['id' => $s['product_id']]) ?>"><?= icon('box', 15) ?> Voir la fiche article</a><?php endif; ?>
      </div>
    </div>

    <?php if ($pending): ?>
    <form method="post" action="<?= url('admin/suggestion/add', ['id' => $s['id']]) ?>" enctype="multipart/form-data" class="card" style="border:2px solid var(--primary)">
      <?= csrf_field() ?>
      <div class="card-head"><h2><?= icon('plus') ?> Compléter et ajouter au catalogue</h2></div>
      <div class="card-body form-grid">
        <div class="field full"><label>Désignation *</label><input type="text" name="name" value="<?= e(trim($s['name'] . ($s['brand'] && !str_contains(mb_strtolower($s['name']), mb_strtolower($s['brand'])) ? ' — ' . $s['brand'] : ''))) ?>" required></div>
        <div class="field"><label>Fournisseur *</label>
          <select name="supplier_id"><option value="">— nouveau fournisseur ci-dessous —</option><?php foreach ($suppliers as $sp): ?><option value="<?= $sp['id'] ?>" <?= $guess === (int)$sp['id'] ? 'selected' : '' ?>><?= e($sp['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>… ou créer le fournisseur</label><input type="text" name="new_supplier" value="<?= $guess ? '' : e($s['supplier_hint']) ?>" placeholder="Nom du nouveau fournisseur"></div>
        <div class="field"><label>Catégorie</label><select name="category_id"><option value="">—</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Référence</label><input type="text" name="reference" value="<?= e($s['reference']) ?>"></div>
        <div class="field"><label>Code-barres</label><input type="text" name="barcode" value="<?= e($s['barcode']) ?>"></div>
        <div class="field"><label>Conditionnement</label><input type="text" name="unit" value="<?= e($s['unit']) ?>"></div>
        <div class="field"><label>Tarif catalogue HT (€)</label><input type="text" name="catalog_price" value="<?= $s['estimated_price'] ? e(number_format((float)$s['estimated_price'], 2, ',', '')) : '' ?>" placeholder="0,00"></div>
        <div class="field"><label>Tarif négocié HT (€)</label><input type="text" name="negotiated_price" placeholder="optionnel"></div>
        <div class="field"><label>TVA (%)</label><input type="text" name="vat_rate" value="20"></div>
        <div class="field full"><label>Descriptif</label><textarea name="description" rows="3"><?= e($s['description']) ?></textarea></div>
        <div class="field full"><label>Mots-clés de recherche</label><input type="text" name="keywords" value="<?= e(trim(($s['brand'] ?? '') . ' ' . ($s['supplier_hint'] ?? ''))) ?>"></div>
        <div class="field"><label>Photo</label><input type="file" name="image" accept="image/*"><small><?= $s['image'] ? 'Laissez vide pour garder la photo du salarié.' : 'Optionnelle.' ?></small></div>
        <div class="field"><label>Message au salarié (optionnel)</label><input type="text" name="admin_note" placeholder="ex : commandé chez notre fournisseur habituel"></div>
      </div>
      <div class="card-foot row"><small class="muted"><?= $s['qty'] && $s['request_id'] ? 'La demande de ' . (int)$s['qty'] . ' unité(s) sera placée dans « Demandes à traiter ».' : 'Aucune quantité demandée : l\'article enrichit simplement le catalogue.' ?></small><span class="spacer"></span><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Ajouter au catalogue</button></div>
    </form>
    <?php endif; ?>
  </div>

  <div class="stack">
    <?php if ($pending): ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('search', 18) ?> Existe-t-il déjà ?</h3></div>
      <?php if ($similar): ?><ul class="list"><?php foreach ($similar as $p): ?>
        <li><?php partial('thumb', ['p' => $p, 'size' => 34]); ?><div class="grow"><a class="title" href="<?= url('admin/product', ['id' => $p['id']]) ?>" target="_blank"><?= e($p['name']) ?></a><div><small><?= e($p['supplier_name']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></small></div></div></li>
      <?php endforeach; ?></ul><?php else: ?><div class="card-body"><small class="muted">Aucun article ressemblant au catalogue.</small></div><?php endif; ?>
      <form method="post" action="<?= url('admin/suggestion/link', ['id' => $s['id']]) ?>" class="card-body" style="border-top:1px solid var(--border)">
        <?= csrf_field() ?>
        <label>Rattacher à un article existant</label>
        <select name="product_id" required class="mb-1">
          <option value="">Choisir…</option>
          <?php $simIds = array_column($similar, 'id'); foreach ($similar as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          <optgroup label="Tout le catalogue"><?php foreach ($allProducts as $p): if (in_array($p['id'], $simIds)) continue; ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></optgroup>
        </select>
        <input type="text" name="admin_note" placeholder="Message au salarié (optionnel)" class="mb-1">
        <button class="btn btn-blue" type="submit" style="width:100%"><?= icon('repeat', 16) ?> Rattacher</button>
        <small class="muted">Le code-barres scanné est ajouté à la fiche s'il manquait<?= $s['qty'] ? ', et la demande est reportée sur cet article' : '' ?>.</small>
      </form>
    </div>
    <form method="post" action="<?= url('admin/suggestion/reject', ['id' => $s['id']]) ?>" class="card card-body" onsubmit="return confirm('Refuser cette proposition ?')">
      <?= csrf_field() ?>
      <h3><?= icon('x', 18) ?> Refuser</h3>
      <input type="text" name="admin_note" placeholder="Motif communiqué au salarié" class="mb-1">
      <button class="btn btn-danger" type="submit">Refuser la proposition</button>
    </form>
    <?php endif; ?>
  </div>
</div>
