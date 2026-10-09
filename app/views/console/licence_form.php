<?php
/** Fiche licence d'un client (console) : $slug, $i (registre), $lr (licence_row), $lic (licence calculée), $back facultatif. */
$back ??= '';
?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="licence_save"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
  <div class="grid2">
    <div><label>Formule</label><input name="plan" value="<?= e($lr['plan']) ?>"></div>
    <div><label>Payé jusqu'au <small class="muted">(vide : sans échéance)</small></label><input type="date" name="paid_until" value="<?= e((string)$lr['paid_until']) ?>"></div>
    <div><label>Prix HT / mois <small class="muted">(vide : <?= e(number_format((float)platform_setting('price_base'), 2, ',', ' ')) ?> €)</small></label><input name="price_base" inputmode="decimal" value="<?= e((string)($lr['price_base'] ?? '')) ?>"></div>
    <div><label>Option IA HT / mois <small class="muted">(vide : <?= e(number_format((float)platform_setting('price_ai'), 2, ',', ' ')) ?> €)</small></label><input name="price_ai" inputmode="decimal" value="<?= e((string)($lr['price_ai'] ?? '')) ?>"></div>
    <div><label>E-mail de facturation</label><input type="email" name="contact_email" value="<?= e((string)$lr['contact_email']) ?>"></div>
    <div><label>Message affiché aux administrateurs du client</label><input name="note" maxlength="300" value="<?= e((string)$lr['note']) ?>"></div>
  </div>
  <label class="check"><input type="checkbox" name="ai" value="1" <?= !empty($lr['ai']) ? 'checked' : '' ?>> Option assistant IA</label>
  <label class="check"><input type="checkbox" name="suspended" value="1" <?= $lr['status'] === 'suspended' ? 'checked' : '' ?>> Licence suspendue <small class="muted">(seuls ses administrateurs peuvent encore se connecter, pour régulariser)</small></label>
  <p><button class="btn sm primary">Enregistrer la licence</button></p>
</form>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="licence_extend"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
  <small class="muted">Paiement reçu hors ligne :</small>
  <select name="months" style="width:auto"><option value="1">1 mois</option><option value="3">3 mois</option><option value="6">6 mois</option><option value="12" selected>12 mois</option></select>
  <button class="btn sm">Prolonger la licence</button>
</form>
<?php if (platform_stripe_ready()): $pay = platform_pay_url($slug); ?>
  <div class="row" style="margin-top:.6rem"><small class="muted">Lien de paiement en ligne :</small> <code style="flex:1"><?= e($pay) ?></code>
    <button type="button" class="btn sm" onclick="navigator.clipboard.writeText(<?= e(json_encode($pay)) ?>);this.textContent='Copié ✓'">Copier</button></div>
<?php endif; ?>
<?php if (platform_hub_linked() && empty($i['public_demo'])): ?>
  <form method="post" class="row" style="margin-top:.6rem"><?= csrf_field() ?><input type="hidden" name="action" value="hub_provision"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
    <small class="muted">Conversation en direct avec l'assistance : <?= $lr['hub_client'] ? 'reliée' : 'non reliée' ?></small>
    <?php if ($lr['hub_client']): ?><label class="check" style="margin:0"><input type="checkbox" name="new_key" value="1"> nouvelle clé</label><?php endif; ?>
    <button class="btn sm"><?= $lr['hub_client'] ? 'Mettre à jour' : 'Relier à l\'assistance' ?></button></form>
<?php endif; ?>
