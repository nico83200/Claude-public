<?php $v = fn($k, $d = '') => e((string)($c[$k] ?? $d)); ?>
<div class="breadcrumb"><a href="<?= url('admin/contracts') ?>">Contrats et marchés</a> <?= icon('chevron-right', 14) ?> <?= $isNew ? 'Nouveau' : e($c['name']) ?></div>
<div class="page-head">
  <div><h1><?= $isNew ? 'Nouveau contrat' : e($c['name']) ?></h1>
    <?php if (!$isNew): ?><p><span class="dot" style="background:<?= e($supplier['color']) ?>"></span> <?= e($supplier['name']) ?> · <?= $c['buying_group'] ? e($c['buying_group']) : 'contrat direct' ?><?= $c['reference'] ? ' · n° ' . e($c['reference']) : '' ?></p><?php endif; ?></div>
  <?php if (!$isNew): ?><div class="row"><?= badge($status) ?><?php if ($c['file']): ?><a class="btn" href="<?= url('admin/contract/file', ['id' => $c['id']]) ?>" target="_blank"><?= icon('file', 18) ?> Document du contrat</a><?php endif; ?></div><?php endif; ?>
</div>

<?php if (!$isNew && in_array($status['key'], ['renew', 'expired'], true)): ?>
  <div class="flash flash-<?= $status['key'] === 'expired' ? 'error' : 'info' ?> mb-2"><?= icon('clock') ?>
    <div style="flex:1"><strong><?= $status['key'] === 'expired' ? 'Contrat terminé le ' . e(date_fr($c['end_date'])) : 'Échéance le ' . e(date_fr($c['end_date'])) . ' (' . e(contract_days_label($status['days'])) . ')' ?></strong>.
      <?= $status['key'] === 'expired' ? 'Les articles gardent le dernier prix contractuel : renégociez ou prolongez le contrat.' : ((int)$c['tacit_renewal'] ? 'Reconduction tacite : pour la dénoncer, prévenez le fournisseur avant le ' . e(date_fr(contract_decision_date($c))) . '.' : 'Préparez la renégociation ou la nouvelle consultation.') ?></div>
    <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="renew">
      <select name="months" style="width:auto"><option value="12">12 mois</option><option value="24">24 mois</option><option value="36">36 mois</option><option value="6">6 mois</option></select>
      <button class="btn btn-sm btn-primary" type="submit">Prolonger</button></form>
  </div>
<?php endif; ?>

<div class="grid grid-main">
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="card-head"><h2><?= icon('file') ?> Contrat</h2></div>
    <div class="card-body form-grid">
      <div class="field full"><label>Intitulé *</label><input type="text" name="name" value="<?= $v('name') ?>" required maxlength="150" placeholder="ex : Marché consommables médicaux 2026-2029"></div>
      <div class="field"><label>Fournisseur titulaire *</label>
        <?php if ($isNew): ?><select name="supplier_id" required><option value="">Choisir…</option><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($c['supplier_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
        <?php else: ?><input type="text" value="<?= e($supplier['name']) ?>" disabled><?php endif; ?></div>
      <div class="field"><label>Groupement d'achats</label><input type="text" name="buying_group" value="<?= $v('buying_group') ?>" list="buying-groups" maxlength="80" placeholder="vide = contrat direct">
        <datalist id="buying-groups"><?php foreach (BUYING_GROUPS as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist></div>
      <div class="field"><label>N° de marché / contrat</label><input type="text" name="reference" value="<?= $v('reference') ?>" maxlength="80"></div>
      <div class="field"><label>Montant annuel estimé (HT)</label><input type="text" inputmode="decimal" name="annual_amount" value="<?= isset($c['annual_amount']) && $c['annual_amount'] !== null && $c['annual_amount'] !== '' ? e(number_format((float)$c['annual_amount'], 2, ',', '')) : '' ?>" placeholder="ex : 25 000"></div>
      <div class="field"><label>Début *</label><input type="date" name="start_date" value="<?= $v('start_date') ?>" required></div>
      <div class="field"><label>Fin *</label><input type="date" name="end_date" value="<?= $v('end_date') ?>" required></div>
      <div class="field"><label>Alerte avant l'échéance</label><select name="notice_days"><?php foreach ([30 => '1 mois', 60 => '2 mois', 90 => '3 mois', 120 => '4 mois', 180 => '6 mois'] as $d => $l): ?><option value="<?= $d ?>" <?= (int)($c['notice_days'] ?? 90) === $d ? 'selected' : '' ?>><?= $l ?> avant</option><?php endforeach; ?></select>
        <small class="muted">Le temps de renégocier, de lancer une consultation ou de dénoncer la reconduction.</small></div>
      <div class="field"><label>&nbsp;</label><label class="check"><input type="checkbox" name="tacit_renewal" value="1" <?= !empty($c['tacit_renewal']) ? 'checked' : '' ?>> Reconduction tacite</label></div>
      <div class="field full"><label>Interlocuteur</label><input type="text" name="contact" value="<?= $v('contact') ?>" maxlength="190" placeholder="ex : Mme Durand, chargée de marché — 04 94 00 00 00"></div>
      <div class="field full"><label>Document du contrat <small class="muted">(PDF, conservé hors du site)</small></label><input type="file" name="file" accept="application/pdf,image/jpeg,image/png">
        <?php if (!$isNew && $c['file']): ?><small><a href="<?= url('admin/contract/file', ['id' => $c['id']]) ?>" target="_blank">Voir le document actuel</a> · un nouvel envoi le remplace</small><?php endif; ?></div>
      <div class="field full"><label>Notes</label><textarea name="notes" rows="2" placeholder="ex : révision des prix au 1er janvier selon l'indice…"><?= $v('notes') ?></textarea></div>
    </div>
    <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> <?= $isNew ? 'Créer le contrat' : 'Enregistrer' ?></button></div>
  </form>

  <div class="stack">
    <?php if (!$isNew): $total = max(1, strtotime($c['end_date']) - strtotime($c['start_date'])); $pct = max(0, min(100, (time() - strtotime($c['start_date'])) / $total * 100)); ?>
    <div class="card card-body">
      <h3 class="mt-0">Calendrier</h3>
      <div class="progress <?= $status['key'] === 'renew' ? 'warn' : ($status['key'] === 'active' ? 'ok' : '') ?>"><span style="width:<?= round($pct) ?>%"></span></div>
      <div class="row mt-1" style="justify-content:space-between"><small><?= e(date_fr($c['start_date'])) ?></small><small><?= e(date_fr($c['end_date'])) ?></small></div>
      <p class="mb-0"><small class="muted">Alerte au service achats le <strong><?= e(date_fr(contract_decision_date($c))) ?></strong>, puis à l'échéance.</small></p>
    </div>
    <?php endif; ?>
    <div class="card card-body">
      <h3 class="mt-0"><?= icon('info', 18) ?> Comment ça marche</h3>
      <p class="muted mb-0"><small>Pendant la durée du contrat, ses prix deviennent le <strong>tarif négocié</strong> des articles : ils s'appliquent aux paniers, aux bons de commande et aux comparaisons. Un import de tarifs fournisseur ne peut pas les écraser. À l'échéance, vous êtes prévenu ; prolongez le contrat ou saisissez le nouveau.</small></p>
    </div>
    <?php if (!$isNew): ?>
    <form method="post" class="card card-body" onsubmit="return confirm('Supprimer ce contrat ? Les articles gardent leur tarif actuel.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <button class="btn btn-ghost btn-danger" type="submit"><?= icon('trash', 16) ?> Supprimer le contrat</button></form>
    <?php endif; ?>
  </div>
</div>

<?php if (!$isNew): $n = count(array_filter($products, fn($p) => $p['contract_price'] !== null)); ?>
<form method="post" class="card mt-2" id="prices">
  <?= csrf_field() ?><input type="hidden" name="action" value="prices">
  <div class="card-head"><h2><?= icon('euro') ?> Prix contractuels <span class="badge badge-violet"><?= plural($n, 'article', 'articles') ?></span></h2>
    <div class="row"><input type="search" placeholder="Filtrer les articles…" data-filter-rows="#contract-rows" style="max-width:220px">
      <button class="btn btn-sm" type="submit" form="take-current" title="Reprend le tarif négocié actuel des articles qui n'ont pas encore de prix dans ce contrat">Reprendre les tarifs négociés actuels</button></div></div>
  <?php if ($products): ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Article</th><th>Réf.</th><th class="num">Tarif catalogue</th><th class="num">Tarif actuel</th><th class="num">Prix contractuel HT</th><th class="num">Remise</th></tr></thead>
      <tbody id="contract-rows">
      <?php foreach ($products as $p): $cp = $p['contract_price']; $disc = $cp !== null && (float)$p['catalog_price'] > 0 ? round((1 - $cp / $p['catalog_price']) * 100) : null; ?>
        <tr data-row-text="<?= e(mb_strtolower($p['name'] . ' ' . $p['reference'])) ?>">
          <td><a href="<?= url('admin/product', ['id' => $p['id']]) ?>"><?= e($p['name']) ?></a><?= $p['unit'] ? ' <small class="muted">' . e($p['unit']) . '</small>' : '' ?><?= $p['active'] ? '' : ' <span class="badge badge-gray">inactif</span>' ?></td>
          <td><small><?= e($p['reference']) ?></small></td>
          <td class="num"><?= money($p['catalog_price']) ?></td>
          <td class="num"><?= $p['negotiated_price'] !== null ? money($p['negotiated_price']) : '<span class="muted">—</span>' ?></td>
          <td class="num"><input class="qty-input" style="width:100px;text-align:right" type="text" inputmode="decimal" name="price[<?= (int)$p['id'] ?>]" value="<?= $cp !== null ? e(number_format((float)$cp, 2, ',', '')) : '' ?>" placeholder="—"></td>
          <td class="num"><?= $disc !== null ? '<span class="badge ' . ($disc > 0 ? 'badge-green' : 'badge-gray') . '">' . ($disc > 0 ? '−' : '') . abs($disc) . ' %</span>' : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?><div class="empty"><?= icon('box') ?><p>Aucun article chez ce fournisseur : ajoutez-les au catalogue (ou importez son tarif), puis revenez saisir les prix.</p></div><?php endif; ?>
  <div class="card-body">
    <details><summary class="strong" style="cursor:pointer">Coller l'annexe tarifaire (référence ; prix)</summary>
      <p class="muted"><small>Copiez deux colonnes depuis Excel ou le bordereau de prix : la référence (ou le code-barres) puis le prix HT. Une ligne par article ; les articles du fournisseur sont reconnus automatiquement.</small></p>
      <textarea name="paste" rows="5" placeholder="GN-NIT-M	4,90&#10;CMP-STE-10;2,15"></textarea>
    </details>
  </div>
  <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Enregistrer les prix</button> <small class="muted">Champ vide = article hors contrat.</small></div>
</form>
<form method="post" id="take-current"><?= csrf_field() ?><input type="hidden" name="action" value="take_current"></form>
<?php endif; ?>
