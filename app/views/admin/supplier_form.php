<?php $s ??= []; $v = fn($k, $d = '') => e($s[$k] ?? $d); ?>
<div class="breadcrumb"><a href="<?= url('admin/suppliers') ?>">Fournisseurs</a> <?= icon('chevron-right', 14) ?> <?= $s ? e($s['name'] ?? '') : 'Nouveau' ?></div>
<h1 class="mb-2"><?= e($title) ?></h1>
<form method="post" class="grid grid-main">
  <?= csrf_field() ?>
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('truck') ?> Identité &amp; contact</h2></div>
      <div class="card-body form-grid">
        <div class="field full"><label>Nom du fournisseur *</label><input type="text" name="name" value="<?= $v('name') ?>" required></div>
        <div class="field"><label>Contact commercial</label><input type="text" name="contact_name" value="<?= $v('contact_name') ?>"></div>
        <div class="field"><label>N° client chez ce fournisseur</label><input type="text" name="customer_number" value="<?= $v('customer_number') ?>"></div>
        <div class="field"><label>E-mail de commande</label><input type="email" name="email" value="<?= $v('email') ?>"></div>
        <div class="field"><label>Téléphone</label><input type="tel" name="phone" value="<?= $v('phone') ?>"></div>
        <div class="field"><label>Site web / espace client</label><input type="url" name="website" value="<?= $v('website') ?>" placeholder="https://"></div>
        <div class="field"><label>Mode de commande</label><input type="text" name="order_method" value="<?= $v('order_method') ?>" placeholder="Site web, e-mail, téléphone, commercial…"></div>
        <div class="field full"><label>Notes internes</label><textarea name="notes"><?= $v('notes') ?></textarea></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2><?= icon('euro') ?> Conditions commerciales</h2></div>
      <div class="card-body form-grid">
        <div class="field"><label>Minimum de commande (€ HT)</label><input type="text" name="min_order_amount" value="<?= e(number_format((float)($s['min_order_amount'] ?? 0), 2, ',', '')) ?>"><small>Les demandes s'accumulent jusqu'à ce montant ; un avertissement s'affiche s'il n'est pas atteint.</small></div>
        <div class="field"><label>Délai de livraison indicatif</label><input type="text" name="delivery_delay" value="<?= $v('delivery_delay') ?>" placeholder="ex : 48 h, 5 jours ouvrés"></div>
        <div class="field"><label>Frais de port (€ HT)</label><input type="text" name="shipping_fee" value="<?= e(number_format((float)($s['shipping_fee'] ?? 0), 2, ',', '')) ?>"></div>
        <div class="field"><label>Franco de port à partir de (€ HT)</label><input type="text" name="free_shipping_from" value="<?= e(number_format((float)($s['free_shipping_from'] ?? 0), 2, ',', '')) ?>"><small>0 = pas de franco.</small></div>
      </div>
    </div>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('building') ?> Centres desservis</h2></div>
      <div class="card-body">
        <label class="check"><input type="radio" name="all_centers" value="1" <?= ($s['all_centers'] ?? 1) ? 'checked' : '' ?> data-toggle-target="#sup-centers" data-toggle-hide> Tous les centres (par défaut)</label>
        <label class="check"><input type="radio" name="all_centers" value="0" <?= !($s['all_centers'] ?? 1) ? 'checked' : '' ?> data-toggle-target="#sup-centers"> Uniquement certains centres</label>
        <div id="sup-centers" class="mt-1 <?= ($s['all_centers'] ?? 1) ? 'hidden' : '' ?>" style="padding:.75rem;background:var(--surface-2);border-radius:12px">
          <?php foreach ($centers as $c): ?><label class="check"><input type="checkbox" name="centers[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $selected, true) ? 'checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach; ?>
        </div>
        <small class="muted">Les articles d'un fournisseur restreint ne sont visibles que dans les centres cochés.</small>
      </div>
    </div>
    <div class="card card-body">
      <label>Couleur</label>
      <div class="swatches mb-2">
        <?php foreach (palette() as $i => $col): ?><input type="radio" name="color" id="sc<?= $i ?>" value="<?= $col ?>" <?= ($s['color'] ?? '#0ea5e9') === $col ? 'checked' : '' ?>><label for="sc<?= $i ?>" style="background:<?= $col ?>"></label><?php endforeach; ?>
      </div>
      <label class="check"><input type="checkbox" name="active" value="1" <?= ($s['active'] ?? 1) ? 'checked' : '' ?>> Fournisseur actif</label>
      <button class="btn btn-primary btn-lg mt-1" type="submit"><?= icon('check', 18) ?> Enregistrer</button>
    </div>
  </div>
</form>
