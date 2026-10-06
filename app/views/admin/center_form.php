<?php $c ??= []; $v = fn($k, $d = '') => e($c[$k] ?? $d); $same = (int)($c['billing_same'] ?? 1) === 1; ?>
<div class="breadcrumb"><a href="<?= url('admin/centers') ?>">Centres</a> <?= icon('chevron-right', 14) ?> <?= e($c['name'] ?? 'Nouveau') ?></div>
<h1 class="mb-2"><?= e($title) ?></h1>
<form method="post" class="grid grid-main">
  <?= csrf_field() ?>
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('building') ?> Identité</h2></div>
      <div class="card-body form-grid">
        <div class="field"><label>Nom du centre *</label><input type="text" name="name" value="<?= $v('name') ?>" required placeholder="ex : IMSS Toulon"></div>
        <div class="field"><label>Code interne</label><input type="text" name="code" value="<?= $v('code') ?>" placeholder="ex : TLN"></div>
        <div class="field full"><label>Raison sociale (entité juridique)</label><input type="text" name="legal_name" value="<?= $v('legal_name') ?>" placeholder="ex : SAS Institut Médical Sport Santé Toulon"><small>Utilisée sur les bons de commande et pour la facturation si elle diffère du nom du centre.</small></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= icon('phone') ?> Contact</h2></div>
      <div class="card-body form-grid">
        <div class="field full"><label>Personne à contacter</label><input type="text" name="contact_name" value="<?= $v('contact_name') ?>" placeholder="ex : Sophie Martin, responsable de centre"></div>
        <div class="field"><label>E-mail du centre</label><input type="email" name="email" value="<?= $v('email') ?>" placeholder="accueil@…"></div>
        <div class="field"><label>Téléphone</label><input type="tel" name="phone" value="<?= $v('phone') ?>"></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= icon('truck') ?> Adresse de livraison</h2></div>
      <div class="card-body form-grid">
        <div class="field full"><label>Adresse</label><input type="text" name="address" value="<?= $v('address') ?>" placeholder="n° et rue"></div>
        <div class="field full"><label>Complément</label><input type="text" name="address2" value="<?= $v('address2') ?>" placeholder="bâtiment, étage, quai de livraison…"></div>
        <div class="field full"><label>Code postal et ville</label><input type="text" name="city" value="<?= $v('city') ?>" placeholder="83000 Toulon"></div>
        <div class="field full"><label>Consignes de livraison</label><textarea name="delivery_info" rows="3" placeholder="Horaires de réception, accès, interlocuteur sur place…"><?= $v('delivery_info') ?></textarea><small>Reprises sur les bons de commande.</small></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= icon('euro') ?> Facturation</h2></div>
      <div class="card-body">
        <label class="check"><input type="checkbox" name="billing_same" value="1" <?= $same ? 'checked' : '' ?> data-toggle-target="#billing-fields" data-invert> Adresse de facturation identique à l'adresse de livraison</label>
        <div id="billing-fields" class="form-grid mt-1 <?= $same ? 'hidden' : '' ?>">
          <div class="field full"><label>Facturer à (nom ou raison sociale)</label><input type="text" name="billing_name" value="<?= $v('billing_name') ?>" placeholder="ex : IMSS — Service comptabilité"></div>
          <div class="field full"><label>Adresse de facturation</label><input type="text" name="billing_address" value="<?= $v('billing_address') ?>"></div>
          <div class="field full"><label>Code postal et ville</label><input type="text" name="billing_city" value="<?= $v('billing_city') ?>"></div>
        </div>
        <div class="form-grid mt-1">
          <div class="field"><label>E-mail de facturation (comptabilité)</label><input type="email" name="billing_email" value="<?= $v('billing_email') ?>" placeholder="compta@…"><small>À défaut, l'e-mail du centre.</small></div>
          <div class="field"><label>Mentions de facturation</label><input type="text" name="billing_notes" value="<?= $v('billing_notes') ?>" placeholder="ex : n° de bon obligatoire sur facture"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('shield') ?> Identifiants légaux</h2></div>
      <div class="card-body">
        <div class="field"><label>SIRET de l'établissement</label><input type="text" name="siret" id="siret" value="<?= $v('siret') ?>" inputmode="numeric" maxlength="17" placeholder="14 chiffres" data-legal="siret"><small data-legal-msg="siret"></small></div>
        <div class="field"><label>SIREN</label><input type="text" name="siren" id="siren" value="<?= $v('siren') ?>" inputmode="numeric" maxlength="11" placeholder="9 chiffres (déduit du SIRET)" data-legal="siren"><small data-legal-msg="siren"></small></div>
        <div class="field"><label>N° de TVA intracommunautaire</label>
          <div class="input-group"><input type="text" name="vat_number" id="vat_number" value="<?= $v('vat_number') ?>" placeholder="FR…"><button class="btn" type="button" data-vat-from-siren title="Calculer à partir du SIREN"><?= icon('repeat', 16) ?></button></div>
          <small>Calculé automatiquement à partir du SIREN s'il est laissé vide.</small></div>
        <div class="field"><label>N° FINESS</label><input type="text" name="finess" value="<?= $v('finess') ?>" maxlength="9" placeholder="ex : 830012345"><small>Fichier national des établissements sanitaires et sociaux.</small></div>
        <small class="muted">Les numéros sont contrôlés (clé de Luhn pour le SIREN et le SIRET, cohérence SIREN / SIRET / TVA).</small>
      </div>
    </div>
    <div class="card card-body">
      <label>Couleur</label>
      <div class="swatches mb-2"><?php foreach (palette() as $i => $col): ?><input type="radio" name="color" id="cc<?= $i ?>" value="<?= $col ?>" <?= ($c['color'] ?? '#6366f1') === $col ? 'checked' : '' ?>><label for="cc<?= $i ?>" style="background:<?= $col ?>"></label><?php endforeach; ?></div>
      <label class="check"><input type="checkbox" name="active" value="1" <?= ($c['active'] ?? 1) ? 'checked' : '' ?>> Centre actif</label>
      <button class="btn btn-primary btn-lg mt-1" type="submit"><?= icon('check', 18) ?> Enregistrer</button>
    </div>
  </div>
</form>
