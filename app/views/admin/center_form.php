<?php $c ??= []; $v = fn($k, $d = '') => e($c[$k] ?? $d); ?>
<div class="breadcrumb"><a href="<?= url('admin/centers') ?>">Centres</a> <?= icon('chevron-right', 14) ?> <?= e($c['name'] ?? 'Nouveau') ?></div>
<h1 class="mb-2"><?= e($title) ?></h1>
<form method="post" class="card" style="max-width:860px">
  <?= csrf_field() ?>
  <div class="card-body form-grid">
    <div class="field"><label>Nom du centre *</label><input type="text" name="name" value="<?= $v('name') ?>" required></div>
    <div class="field"><label>Code interne</label><input type="text" name="code" value="<?= $v('code') ?>" placeholder="ex : CDS-NORD"></div>
    <div class="field full"><label>Adresse de livraison</label><input type="text" name="address" value="<?= $v('address') ?>"></div>
    <div class="field"><label>Code postal &amp; ville</label><input type="text" name="city" value="<?= $v('city') ?>"></div>
    <div class="field"><label>Téléphone</label><input type="tel" name="phone" value="<?= $v('phone') ?>"></div>
    <div class="field full"><label>Instructions de livraison</label><textarea name="delivery_info" placeholder="Horaires de réception, accès, interlocuteur…"><?= $v('delivery_info') ?></textarea><small>Reprises sur les bons de commande imprimés.</small></div>
    <div class="field full"><label>Couleur</label><div class="swatches"><?php foreach (palette() as $i => $col): ?><input type="radio" name="color" id="cc<?= $i ?>" value="<?= $col ?>" <?= ($c['color'] ?? '#6366f1') === $col ? 'checked' : '' ?>><label for="cc<?= $i ?>" style="background:<?= $col ?>"></label><?php endforeach; ?></div></div>
    <label class="check full"><input type="checkbox" name="active" value="1" <?= ($c['active'] ?? 1) ? 'checked' : '' ?>> Centre actif</label>
  </div>
  <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Enregistrer</button></div>
</form>
