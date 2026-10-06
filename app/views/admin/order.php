<?php
$editable = $po['status'] === 'a_commander';
$order = ['a_commander', 'commande', 'partiel', 'recu'];
$idx = array_search($po['status'], $order, true);
$grand = $totals['total'] + (float)$po['shipping_fee'];
$minOk = (float)$po['min_order_amount'] <= 0 || $totals['total'] >= (float)$po['min_order_amount'];
?>
<div class="breadcrumb"><a href="<?= url('admin/orders', ['status' => 'open']) ?>">Bons de commande</a> <?= icon('chevron-right', 14) ?> <?= e($po['po_number']) ?></div>
<div class="page-head">
  <div>
    <h1><?= e($po['po_number']) ?> <?= po_status_badge($po['status']) ?></h1>
    <p><strong><?= e($po['supplier_name']) ?></strong> → <?= e($po['center_name']) ?> · créé le <?= date_fr($po['created_at'], true) ?><?= $po['creator_first'] ? ' par ' . e($po['creator_first'] . ' ' . $po['creator_last']) : '' ?></p>
  </div>
  <div class="row row-wrap">
    <?php if ($po['group_ref']): ?><a class="btn" href="<?= url('admin/order-group', ['ref' => $po['group_ref']]) ?>"><?= icon('layers', 18) ?> Groupe <?= e($po['group_ref']) ?></a><?php endif; ?>
    <a class="btn" href="<?= url('admin/order/pdf', ['id' => $po['id']]) ?>" target="_blank"><?= icon('file', 18) ?> PDF</a>
    <a class="btn" href="<?= url('admin/order/print', ['id' => $po['id']]) ?>" target="_blank"><?= icon('printer', 18) ?> Imprimer</a>
    <a class="btn" href="<?= url('admin/order/csv', ['id' => $po['id']]) ?>"><?= icon('download', 18) ?> CSV</a>
    <?php if ($po['supplier_email']): ?>
      <a class="btn" href="mailto:<?= e($po['supplier_email']) ?>?subject=<?= rawurlencode('Commande ' . $po['po_number'] . ' — ' . $po['center_name']) ?>&body=<?= rawurlencode("Bonjour,\n\nVeuillez trouver ci-joint notre bon de commande " . $po['po_number'] . ($po['customer_number'] ? ' (n° client ' . $po['customer_number'] . ')' : '') . " pour une livraison à :\n" . $po['center_name'] . "\n" . $po['center_address'] . ' ' . $po['center_city'] . "\n\nCordialement,\n" . user()['first_name'] . ' ' . user()['last_name']) ?>"><?= icon('mail', 18) ?> E-mail</a>
    <?php endif; ?>
  </div>
</div>

<div class="steps mb-2">
  <?php if ($po['status'] === 'annule'): ?>
    <span class="step current" style="background:#9ca3af;box-shadow:none"><?= icon('x', 16) ?> Annulé</span>
  <?php else: foreach (['a_commander' => 'À commander', 'commande' => 'Commandé', 'partiel' => 'Reçu partiellement', 'recu' => 'Reçu'] as $k => $lbl):
    $i = array_search($k, $order, true);
    if ($k === 'partiel' && $po['status'] !== 'partiel') continue; ?>
    <span class="step <?= $i < $idx ? 'done' : ($i === $idx ? 'current' : '') ?>"><?= $i < $idx ? icon('check', 15) : '' ?> <?= e($lbl) ?></span>
    <?php if ($k !== 'recu'): ?><span class="step-sep"></span><?php endif; ?>
  <?php endforeach; endif; ?>
</div>

<div class="grid grid-main">
  <div class="stack">
    <form method="post" action="<?= url('admin/order/lines', ['id' => $po['id']]) ?>" class="card">
      <?= csrf_field() ?>
      <div class="card-head"><h2><?= icon('box') ?> Lignes du bon</h2><?php if ($editable): ?><small class="muted">Modifiable tant que le bon n'est pas commandé</small><?php endif; ?></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Article</th><th>Demandeurs</th><th class="num">Qté</th><th class="num">Prix u. HT</th><th class="num">Total HT</th><?php if (!$editable): ?><th class="num">Reçu</th><?php else: ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): $req = $requesters[(int)$l['product_id']] ?? []; ?>
          <tr>
            <td><div class="row"><?php partial('thumb', ['p' => $l, 'size' => 36]); ?><div><div class="strong"><?= e($l['label']) ?></div><small><?= $l['reference'] ? 'Réf. ' . e($l['reference']) . ' · ' : '' ?><?= e($l['unit']) ?></small><?php if ((float)$l['catalog_price'] > (float)$l['unit_price']): ?> <span class="badge badge-green" title="Tarif catalogue <?= e(money($l['catalog_price'])) ?>">négocié</span><?php endif; ?></div></div></td>
            <td><?php foreach ($req as $r): ?><div><small><?= e($r['first_name'] . ' ' . $r['last_name']) ?> (<?= (int)$r['qty'] ?>)<?= $r['urgent'] ? ' <span class="badge badge-red">Urgent</span>' : '' ?><?= $r['comment'] ? ' — « ' . e($r['comment']) . ' »' : '' ?></small></div><?php endforeach; ?><?php if (!$req): ?><small class="muted">Ajout manuel</small><?php endif; ?></td>
            <?php if ($editable): ?>
              <td class="num"><input class="qty-input" type="number" min="0" name="qty[<?= (int)$l['id'] ?>]" value="<?= (int)$l['qty'] ?>"></td>
              <td class="num"><input type="text" name="price[<?= (int)$l['id'] ?>]" value="<?= e(number_format((float)$l['unit_price'], 2, ',', '')) ?>" style="width:90px;text-align:right"></td>
              <td class="num strong"><?= money($l['qty'] * $l['unit_price']) ?></td>
              <td><label class="check mb-0" title="Retirer du bon (les demandes repassent en attente)"><input type="checkbox" name="remove[<?= (int)$l['id'] ?>]" value="1"> <?= icon('trash', 15) ?></label></td>
            <?php else: ?>
              <td class="num"><?= (int)$l['qty'] ?></td>
              <td class="num"><?= money($l['unit_price']) ?></td>
              <td class="num strong"><?= money($l['qty'] * $l['unit_price']) ?></td>
              <td class="num"><?= (int)$l['qty_received'] >= (int)$l['qty'] ? '<span class="badge badge-green">' . (int)$l['qty_received'] . '</span>' : '<span class="badge badge-amber">' . (int)$l['qty_received'] . '</span>' ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="4" class="text-right">Sous-total HT</td><td class="num"><?= money($totals['total']) ?></td><td></td></tr>
          <tr><td colspan="4" class="text-right">Frais de port <?= $po['free_shipping_from'] > 0 ? '<small class="muted">(franco dès ' . money($po['free_shipping_from']) . ')</small>' : '' ?></td>
            <td class="num"><?php if ($editable): ?><input type="text" name="shipping_fee" value="<?= e(number_format((float)$po['shipping_fee'], 2, ',', '')) ?>" style="width:90px;text-align:right"><?php else: ?><?= money($po['shipping_fee']) ?><?php endif; ?></td><td></td></tr>
          <tr><td colspan="4" class="text-right">Total HT</td><td class="num" style="font-size:1.1rem"><?= money($grand) ?></td><td></td></tr>
        </tfoot>
      </table></div>
      <?php if ($editable): ?>
      <div class="card-body">
        <label>Note / instructions</label>
        <textarea name="notes" rows="2"><?= e($po['notes']) ?></textarea>
      </div>
      <div class="card-foot row"><span class="spacer"></span><button class="btn" type="submit"><?= icon('check', 16) ?> Enregistrer les modifications</button></div>
      <?php elseif ($po['notes']): ?>
      <div class="card-body"><small class="muted">Note :</small> <?= nl2br(e($po['notes'])) ?></div>
      <?php endif; ?>
    </form>

    <?php if ($editable): ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('plus', 18) ?> Compléter le bon</h3></div>
      <div class="card-body">
        <?php if ($otherPending): ?>
        <form method="post" action="<?= url('admin/order/add-line', ['id' => $po['id']]) ?>" class="row mb-2">
          <?= csrf_field() ?><input type="hidden" name="mode" value="pending">
          <span class="badge badge-amber"><?= plural($otherPending, 'nouvelle demande', 'nouvelles demandes') ?> en attente pour ce centre et ce fournisseur</span>
          <button class="btn btn-sm btn-amber" type="submit">Les intégrer</button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= url('admin/order/add-line', ['id' => $po['id']]) ?>" class="row row-wrap">
          <?= csrf_field() ?>
          <select name="product_id" style="flex:1;min-width:240px" required>
            <option value="">Ajouter un article du fournisseur…</option>
            <?php foreach ($supplierProducts as $sp): ?><option value="<?= (int)$sp['id'] ?>"><?= e($sp['name']) ?><?= $sp['reference'] ? ' — ' . e($sp['reference']) : '' ?></option><?php endforeach; ?>
          </select>
          <input class="qty-input" type="number" name="qty" min="1" value="1">
          <button class="btn" type="submit"><?= icon('plus', 16) ?> Ajouter</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><?= icon('activity', 18) ?> Actions</h3></div>
      <div class="card-body">
        <?php if ($po['status'] === 'a_commander'): ?>
          <?php if ($po['group_ref']): ?><div class="flash flash-info" style="font-size:.85rem"><?= icon('layers', 18) ?><div>Ce bon fait partie de la commande groupée <a href="<?= url('admin/order-group', ['ref' => $po['group_ref']]) ?>"><?= e($po['group_ref']) ?></a> : envoyez-la depuis la page du groupe.</div></div><?php endif; ?>
          <?php if (!mail_case_enabled('supplier_po')): ?>
            <div class="flash flash-info" style="font-size:.85rem"><?= icon('mail', 16) ?><div>Envoi des bons par e-mail désactivé. <a href="<?= url('admin/settings') ?>">Paramètres</a> · <a href="<?= url('admin/order/pdf', ['id' => $po['id'], 'dl' => 1]) ?>">Télécharger le PDF</a></div></div>
          <?php else: ?>
          <form method="post" action="<?= url('admin/order/send', ['id' => $po['id']]) ?>" class="mb-2" style="padding-bottom:1rem;border-bottom:1px solid var(--border)">
            <?= csrf_field() ?>
            <label><?= icon('send', 15) ?> Envoyer le bon (PDF) au fournisseur</label>
            <input type="email" name="to" value="<?= e($po['supplier_email']) ?>" required placeholder="adresse de commande du fournisseur" class="mb-1">
            <label class="check" style="font-weight:500"><input type="checkbox" name="mark_ordered" value="1" checked> et passer le bon en « Commandé »</label>
            <?php if (mail_case_enabled('supplier_copy')): ?><label class="check" style="font-weight:500"><input type="checkbox" name="cc_me" value="1"> m'envoyer une copie</label><?php endif; ?>
            <button class="btn btn-blue" style="width:100%" type="submit"><?= icon('send', 16) ?> Envoyer par e-mail</button>
            <?php if ($po['sent_to_supplier_at']): ?><small class="muted">Déjà envoyé le <?= date_fr($po['sent_to_supplier_at'], true) ?></small><?php endif; ?>
          </form>
          <?php endif; ?>
          <?php if (!$minOk): ?><div class="flash flash-error" style="font-size:.85rem"><?= icon('alert', 18) ?><div>Minimum de commande fournisseur : <?= money($po['min_order_amount']) ?> (il manque <?= money((float)$po['min_order_amount'] - $totals['total']) ?>).</div></div><?php endif; ?>
          <form method="post" action="<?= url('admin/order/status', ['id' => $po['id']]) ?>">
            <?= csrf_field() ?><input type="hidden" name="to" value="commande">
            <div class="field"><label>Réf. de commande fournisseur (optionnel)</label><input type="text" name="supplier_reference" placeholder="N° de confirmation, de panier web…"></div>
            <div class="field"><label>Livraison prévue le</label><input type="date" name="expected_date"></div>
            <button class="btn btn-lg" style="width:100%" type="submit"><?= icon('check', 18) ?> Commandé par un autre moyen</button>
          </form>
          <p class="muted mt-1" style="font-size:.82rem">Une fois commandé, le centre pourra cocher les articles reçus.</p>
        <?php elseif (in_array($po['status'], ['commande', 'partiel'], true)): ?>
          <form method="post" action="<?= url('admin/order/status', ['id' => $po['id']]) ?>" class="mb-2">
            <?= csrf_field() ?><input type="hidden" name="to" value="update_ref">
            <div class="field"><label>Réf. fournisseur</label><input type="text" name="supplier_reference" value="<?= e($po['supplier_reference']) ?>"></div>
            <div class="field"><label>Livraison prévue le</label><input type="date" name="expected_date" value="<?= e($po['expected_date']) ?>"></div>
            <button class="btn btn-sm" type="submit">Mettre à jour</button>
          </form>
          <a class="btn btn-success" style="width:100%" href="<?= url('reception', ['id' => $po['id'], 'c' => $po['center_id']]) ?>"><?= icon('package-check', 18) ?> Saisir une réception</a>
          <form method="post" action="<?= url('admin/order/status', ['id' => $po['id']]) ?>" class="mt-1" onsubmit="return confirm('Marquer toutes les lignes comme reçues ?')">
            <?= csrf_field() ?><input type="hidden" name="to" value="recu"><button class="btn btn-sm" style="width:100%" type="submit">Tout marquer reçu</button>
          </form>
          <?php if ($po['status'] === 'commande' && !$totals['received']): ?>
          <form method="post" action="<?= url('admin/order/status', ['id' => $po['id']]) ?>" class="mt-1">
            <?= csrf_field() ?><input type="hidden" name="to" value="a_commander"><button class="btn btn-ghost btn-sm" style="width:100%" type="submit"><?= icon('arrow-left', 15) ?> Revenir à « À commander »</button>
          </form>
          <?php endif; ?>
        <?php elseif ($po['status'] === 'recu'): ?>
          <div class="flash flash-success mb-0"><?= icon('check-circle') ?><div>Commande entièrement reçue le <?= date_fr($po['received_at']) ?>.</div></div>
        <?php else: ?>
          <p class="muted">Bon annulé.</p>
        <?php endif; ?>

        <?php if (in_array($po['status'], ['a_commander', 'commande'], true)): ?>
          <hr>
          <form method="post" action="<?= url('admin/order/status', ['id' => $po['id']]) ?>" onsubmit="return confirm('Annuler ce bon de commande ?')">
            <?= csrf_field() ?><input type="hidden" name="to" value="annule">
            <label class="check"><input type="checkbox" name="requeue" value="1" checked> Remettre les demandes en attente</label>
            <button class="btn btn-danger btn-sm" type="submit"><?= icon('x', 15) ?> Annuler le bon</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if (in_array($po['status'], ['commande', 'partiel', 'recu'], true)): $inv = invoice_check($po); ?>
    <form method="post" action="<?= url('admin/order/invoice', ['id' => $po['id']]) ?>" enctype="multipart/form-data" class="card" id="facture">
      <?= csrf_field() ?>
      <div class="card-head"><h3><?= icon('euro', 18) ?> Facture</h3><?= invoice_badge($inv['status']) ?></div>
      <div class="card-body">
        <div class="row mb-1" style="justify-content:space-between;font-size:.85rem"><span class="muted">Commandé</span><strong><?= money($inv['ordered']) ?></strong></div>
        <div class="row mb-2" style="justify-content:space-between;font-size:.85rem"><span class="muted">Reçu (attendu sur facture)</span><strong><?= money($inv['expected']) ?></strong></div>
        <?php if ($inv['diff'] !== null && $inv['status'] === 'ecart'): ?><div class="flash flash-error" style="font-size:.85rem"><?= icon('alert', 16) ?><div>Écart de <?= money($inv['diff']) ?> : vérifiez les quantités livrées et les prix facturés.</div></div><?php endif; ?>
        <div class="form-grid">
          <div class="field"><label>N° de facture</label><input type="text" name="invoice_number" value="<?= e($po['invoice_number']) ?>"></div>
          <div class="field"><label>Date</label><input type="date" name="invoice_date" value="<?= e($po['invoice_date']) ?>"></div>
        </div>
        <div class="field"><label>Montant HT facturé (€)</label><input type="text" name="invoice_amount" value="<?= $po['invoice_amount'] !== null ? e(number_format((float)$po['invoice_amount'], 2, ',', '')) : '' ?>" inputmode="decimal" placeholder="<?= e(number_format($inv['expected'], 2, ',', '')) ?>"></div>
        <div class="field"><label>Justificatif (PDF ou photo)</label><input type="file" name="invoice_file" accept="application/pdf,image/*">
          <?php if ($po['invoice_file']): ?><small><a href="<?= url('admin/order/invoice-file', ['id' => $po['id']]) ?>" target="_blank"><?= icon('file', 13) ?> Voir la facture enregistrée</a></small><?php endif; ?></div>
        <button class="btn btn-primary" type="submit"><?= icon('check', 16) ?> Enregistrer et rapprocher</button>
      </div>
    </form>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3><?= icon('truck', 18) ?> Fournisseur</h3></div>
      <div class="card-body" style="font-size:.9rem">
        <div class="strong"><?= e($po['supplier_name']) ?></div>
        <?php if ($po['contact_name']): ?><div><?= e($po['contact_name']) ?></div><?php endif; ?>
        <?php if ($po['supplier_phone']): ?><div><?= icon('phone', 14) ?> <?= e($po['supplier_phone']) ?></div><?php endif; ?>
        <?php if ($po['supplier_email']): ?><div><?= icon('mail', 14) ?> <a href="mailto:<?= e($po['supplier_email']) ?>"><?= e($po['supplier_email']) ?></a></div><?php endif; ?>
        <?php if ($po['website']): ?><div><a href="<?= e($po['website']) ?>" target="_blank" rel="noopener"><?= e($po['website']) ?></a></div><?php endif; ?>
        <?php if ($po['customer_number']): ?><div class="mt-1"><span class="badge badge-gray">N° client : <?= e($po['customer_number']) ?></span></div><?php endif; ?>
        <?php if ($po['order_method']): ?><div class="mt-1"><small class="muted">Mode de commande : <?= e($po['order_method']) ?></small></div><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3><?= icon('clock', 18) ?> Historique</h3></div>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
          <li><div class="strong"><?= e($h['action']) ?></div><?php if ($h['details']): ?><small><?= e($h['details']) ?></small><?php endif; ?><div class="when"><?= date_fr($h['created_at'], true) ?><?= $h['first_name'] ? ' · ' . e($h['first_name'] . ' ' . $h['last_name']) : '' ?></div></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
