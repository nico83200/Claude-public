<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title><?= e($po['po_number']) ?></title>
<style>
  body { font-family: Inter, Arial, sans-serif; color: #1e2335; margin: 32px; font-size: 13px; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 4px solid #6366f1; padding-bottom: 16px; margin-bottom: 20px; }
  h1 { margin: 0; font-size: 26px; color: #6366f1; }
  .box { border: 1px solid #e6e9f2; border-radius: 10px; padding: 12px 14px; width: 48%; }
  .boxes { display: flex; justify-content: space-between; margin-bottom: 20px; }
  .label { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #6b7290; margin-bottom: 4px; font-weight: 700; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f4f6fb; text-align: left; padding: 8px; font-size: 11px; text-transform: uppercase; color: #6b7290; }
  td { padding: 8px; border-bottom: 1px solid #e6e9f2; }
  .num { text-align: right; white-space: nowrap; }
  tfoot td { font-weight: 700; border: 0; }
  .total td { font-size: 15px; color: #6366f1; }
  .foot { margin-top: 30px; color: #6b7290; font-size: 11px; }
  .actions { margin-bottom: 20px; }
  @media print { .actions { display: none; } body { margin: 0; } }
</style></head>
<body>
<div class="actions"><button onclick="window.print()">Imprimer / Enregistrer en PDF</button></div>
<div class="head">
  <div>
    <div class="label">Bon de commande</div>
    <h1><?= e($po['po_number']) ?></h1>
    <div>Date : <?= date_fr($po['ordered_at'] ?: $po['created_at']) ?></div>
  </div>
  <div style="text-align:right">
    <?php if ($logo = brand_logo_url()): ?><img src="<?= e($logo) ?>" alt="" style="max-height:70px;max-width:180px;margin-bottom:6px"><br><?php endif; ?>
    <strong style="font-size:16px"><?= e(setting('company_name') ?: app_name()) ?></strong><br>
    <?= nl2br(e(setting('company_address') ?: '')) ?>
  </div>
</div>
<div class="boxes">
  <div class="box">
    <div class="label">Fournisseur</div>
    <strong><?= e($po['supplier_name']) ?></strong><br>
    <?= e($po['contact_name']) ?><?= $po['contact_name'] ? '<br>' : '' ?>
    <?= e($po['supplier_email']) ?> <?= e($po['supplier_phone']) ?><br>
    <?php if ($po['customer_number']): ?>N° client : <strong><?= e($po['customer_number']) ?></strong><?php endif; ?>
  </div>
  <div class="box">
    <div class="label">Adresse de livraison</div>
    <strong><?= e($po['center_name']) ?></strong><br>
    <?php $cx = one('SELECT address2, contact_name, email FROM centers WHERE id = ?', [$po['center_id']]); ?>
    <?= e($po['center_address']) ?><br>
    <?php if ($cx['address2']): ?><?= e($cx['address2']) ?><br><?php endif; ?>
    <?= e($po['center_city']) ?><br>
    <?php if ($cx['contact_name']): ?>Contact : <?= e($cx['contact_name']) ?><br><?php endif; ?>
    <?= $po['center_phone'] ? 'Tél. ' . e($po['center_phone']) . '<br>' : '' ?>
    <?= $po['delivery_info'] ? '<em>' . nl2br(e($po['delivery_info'])) . '</em>' : '' ?>
  </div>
</div>
<?php $c = one('SELECT * FROM centers WHERE id = ?', [$po['center_id']]); $bill = center_billing($c); ?>
<div class="box" style="width:100%;margin-bottom:20px">
  <div class="label">Facturation</div>
  <strong><?= e($bill['name']) ?></strong> — <?= e(trim($bill['address'] . ', ' . $bill['city'], ', ')) ?>
  <?= $bill['email'] ? ' · factures à ' . e($bill['email']) : '' ?>
  <?php $legal = array_filter([$bill['siret'] ? 'SIRET ' . $bill['siret'] : null, $bill['vat'] ? 'TVA ' . $bill['vat'] : null, $bill['finess'] ? 'FINESS ' . $bill['finess'] : null]); if ($legal): ?><br><span style="color:#6b7290"><?= e(implode(' · ', $legal)) ?></span><?php endif; ?>
  <?= $bill['notes'] ? '<br><em>' . e($bill['notes']) . '</em>' : '' ?>
</div>
<table>
  <thead><tr><th>Référence</th><th>Désignation</th><th>Conditionnement</th><th class="num">Qté</th><th class="num">P.U. HT</th><th class="num">Total HT</th></tr></thead>
  <tbody>
  <?php foreach ($lines as $l): ?>
    <tr><td><?= e($l['reference']) ?></td><td><?= e($l['label']) ?></td><td><?= e($l['unit']) ?></td><td class="num"><?= (int)$l['qty'] ?></td><td class="num"><?= money($l['unit_price']) ?></td><td class="num"><?= money($l['qty'] * $l['unit_price']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="5" class="num">Sous-total HT</td><td class="num"><?= money($totals['total']) ?></td></tr>
    <tr><td colspan="5" class="num">Frais de port HT</td><td class="num"><?= money($po['shipping_fee']) ?></td></tr>
    <tr class="total"><td colspan="5" class="num">Total HT</td><td class="num"><?= money($totals['total'] + (float)$po['shipping_fee']) ?></td></tr>
  </tfoot>
</table>
<?php if ($po['notes']): ?><p><strong>Instructions :</strong> <?= nl2br(e($po['notes'])) ?></p><?php endif; ?>
<?php if (setting('billing_info')): ?><div class="foot"><strong>Facturation :</strong> <?= nl2br(e(setting('billing_info'))) ?></div><?php endif; ?>
<div class="foot">Merci de rappeler le numéro <?= e($po['po_number']) ?> sur le bon de livraison et la facture.</div>
</body></html>
