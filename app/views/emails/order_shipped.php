<?php use App\Core\View; use App\Services\OrderService; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#1f2a44;margin:0 0 12px;">Votre colis est en route 🐎</h1>
<p>Bonne nouvelle : votre commande <strong><?= e($order['number']) ?></strong> vient d'être expédiée<?= $order['carrier'] ? ' via ' . e($order['carrier']) : '' ?>.</p>
<?php if ($order['tracking_number']): ?><p>Numéro de suivi : <strong><?= e($order['tracking_number']) ?></strong></p><?php endif; ?>
<?= View::capture('emails/_button', ['href' => $order['tracking_url'] ?: OrderService::trackingUrl($order), 'label' => 'Suivre mon colis']) ?>
<?= View::capture('emails/_items', ['items' => $items]) ?>
<p>Partagez vos photos avec votre cheval en nous identifiant, nous adorons les voir !</p>
