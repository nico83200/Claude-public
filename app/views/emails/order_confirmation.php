<?php use App\Core\View; use App\Services\OrderService; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#5a2a3a;margin:0 0 12px;">Merci <?= e(explode(' ', (string) $order['customer_name'])[0]) ?> !</h1>
<p>Votre commande <strong><?= e($order['number']) ?></strong> est confirmée. Chaque article va être imprimé spécialement pour vous dans notre atelier partenaire, puis expédié sous 2 à 5 jours ouvrés.</p>
<?= View::capture('emails/_items', ['items' => $items, 'order' => $order]) ?>
<p style="margin:0 0 4px;"><strong>Livraison à :</strong></p>
<p style="margin:0;color:#555;"><?= e($address['name'] ?? '') ?><br><?= e($address['line1'] ?? '') ?><?= !empty($address['line2']) ? '<br>' . e($address['line2']) : '' ?><br><?= e(($address['postal_code'] ?? '') . ' ' . ($address['city'] ?? '')) ?> — <?= e($address['country'] ?? '') ?></p>
<?= View::capture('emails/_button', ['href' => OrderService::trackingUrl($order), 'label' => 'Suivre ma commande']) ?>
<p style="font-size:13px;color:#777;">Vous recevrez un e-mail avec le numéro de suivi dès l'expédition. Les articles personnalisés étant fabriqués selon vos spécifications, ils ne sont pas soumis au droit de rétractation ; en cas de défaut, nous les réimprimons gratuitement.</p>
