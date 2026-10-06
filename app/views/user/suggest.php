<?php $o = $old; $scan = $from === 'scan'; ?>
<div class="breadcrumb"><a href="<?= url($scan ? 'catalog' : 'cart') ?>"><?= $scan ? 'Catalogue' : 'Mon panier' ?></a> <?= icon('chevron-right', 14) ?> Proposer un article</div>
<div class="page-head">
  <div>
    <h1><?= $scan && $o['barcode'] ? 'Article inconnu au catalogue' : 'Proposer un article hors catalogue' ?></h1>
    <p><?= $scan && $o['barcode']
        ? 'Le code-barres <strong>' . e($o['barcode']) . '</strong> ne correspond à aucun article. Décrivez-le : le service achats pourra l\'ajouter au catalogue.'
        : 'Vous ne trouvez pas ce qu\'il vous faut ? Donnez le plus d\'informations possible : le service achats complétera et ajoutera l\'article.' ?></p>
  </div>
</div>
<form method="post" enctype="multipart/form-data" class="grid grid-main">
  <?= csrf_field() ?><input type="hidden" name="source" value="<?= e($from) ?>">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('box') ?> L'article</h2></div>
      <div class="card-body form-grid">
        <div class="field full"><label>Nom de l'article *</label><input type="text" name="name" value="<?= e($o['name']) ?>" required minlength="3" placeholder="ex : Spéculums vaginaux jetables taille M" autofocus></div>
        <div class="field"><label>Marque</label><input type="text" name="brand" value="<?= e($o['brand']) ?>" placeholder="ex : Hartmann"></div>
        <div class="field"><label>Référence fabricant / fournisseur</label><input type="text" name="reference" value="<?= e($o['reference']) ?>"></div>
        <div class="field"><label>Conditionnement</label><input type="text" name="unit" value="<?= e($o['unit']) ?>" placeholder="ex : boîte de 50"></div>
        <div class="field"><label>Code-barres</label>
          <div class="input-group"><input type="text" name="barcode" id="sugg-barcode" value="<?= e($o['barcode']) ?>" inputmode="numeric" placeholder="Scanner ou saisir"><button class="btn" type="button" data-scan="fill:#sugg-barcode" title="Scanner"><?= icon('camera', 18) ?></button></div>
        </div>
        <div class="field full"><label>Usage, précisions (taille, couleur, pour quel soin…)</label><textarea name="description" rows="3" placeholder="ex : utilisé pour les frottis, l'ancien modèle n'est plus disponible"><?= e($o['description']) ?></textarea></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2><?= icon('truck') ?> Où le trouver ?</h2></div>
      <div class="card-body form-grid">
        <div class="field"><label>Fournisseur ou magasin connu</label><input type="text" name="supplier_hint" value="<?= e($o['supplier_hint']) ?>" placeholder="ex : MédiDistrib, pharmacie, Amazon…"></div>
        <div class="field"><label>Prix constaté (€, optionnel)</label><input type="text" name="estimated_price" value="<?= e($o['estimated_price']) ?>" inputmode="decimal" placeholder="0,00"></div>
        <div class="field full"><label>Lien vers l'article (site du fournisseur…)</label><input type="url" name="url" value="<?= e($o['url']) ?>" placeholder="https://"></div>
      </div>
    </div>
  </div>
  <div class="stack">
    <div class="card card-body">
      <label><?= icon('camera', 16) ?> Photo de l'article ou de son étiquette</label>
      <input type="file" name="photo" accept="image/*" capture="environment" data-preview="#sugg-preview">
      <img id="sugg-preview" class="hidden mt-1" alt="" style="border-radius:12px;max-height:220px;object-fit:contain;width:100%;background:#fff">
      <small class="muted" style="display:block;margin-top:.4rem">Sur smartphone ou tablette, l'appareil photo s'ouvre directement.</small>
    </div>
    <div class="card card-body">
      <label class="check"><input type="checkbox" name="add_to_cart" value="1" <?= (int)$o['qty'] > 0 ? 'checked' : '' ?> data-toggle-target="#sugg-qty"> J'en ai besoin : l'ajouter à ma demande</label>
      <div id="sugg-qty" class="field mt-1 <?= (int)$o['qty'] > 0 ? '' : 'hidden' ?>"><label>Quantité souhaitée</label><input type="number" name="qty" min="1" value="<?= max(1, (int)$o['qty']) ?>"></div>
      <small class="muted">Sinon, la proposition est simplement envoyée au service achats pour enrichir le catalogue.</small>
      <button class="btn btn-primary btn-lg mt-2" type="submit"><?= icon('send', 18) ?> Envoyer la proposition</button>
    </div>
  </div>
</form>
