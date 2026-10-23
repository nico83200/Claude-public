<?php use App\Core\View; ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#a33;"><?= e($title) ?></h1>
<p><?= nl2br(e($message)) ?></p>
<?php if (!empty($order)): ?>
  <p>Commande <strong><?= e($order['number']) ?></strong> — <?= e($order['customer_name']) ?> (<?= e($order['email']) ?>)</p>
  <?= View::capture('emails/_button', ['href' => url('admin/commandes/' . $order['id']), 'label' => 'Ouvrir la commande']) ?>
<?php endif; ?>
