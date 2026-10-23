<?php use App\Core\View; ?>
<h1 style="font-size:20px;margin:0 0 12px;">Nouvelle commande <?= e($order['number']) ?></h1>
<p><strong><?= e($order['customer_name']) ?></strong> — <?= e($order['email']) ?><?= $order['phone'] ? ' — ' . e($order['phone']) : '' ?></p>
<?= View::capture('emails/_items', ['items' => $items, 'order' => $order]) ?>
<?= View::capture('emails/_button', ['href' => url('admin/commandes/' . $order['id']), 'label' => 'Voir dans l\'administration']) ?>
