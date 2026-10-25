<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#5a2a3a;margin:0 0 12px;">Ta commande est annulée</h1>
<p>Bonjour<?= $order['customer_name'] ? ' ' . e(explode(' ', (string) $order['customer_name'])[0]) : '' ?>,</p>
<p>Ta commande <strong><?= e($order['number']) ?></strong> a été annulée<?= $reason !== '' ? ' : ' . e($reason) : '' ?>.</p>
<?php if ($refunded): ?>
  <p>Le montant de <strong><?= e(money($order['total'])) ?></strong> t'est remboursé sur ton moyen de paiement. Selon ta banque, il apparaît sous 5 à 10 jours ouvrés.</p>
<?php endif; ?>
<?= View::capture('emails/_items', ['items' => $items]) ?>
<p>Une question ? Réponds simplement à cet e-mail, nous sommes là pour toi.</p>
<?= View::capture('emails/_button', ['href' => url('boutique'), 'label' => 'Retourner à la boutique']) ?>
