<span class="status status-<?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span>
<?php if (in_array($order['fulfillment_status'], ['error', 'draft', 'artwork'], true)): ?>
  <span class="status status-<?= e($order['fulfillment_status']) ?>"><?= e(fulfillment_status_label($order['fulfillment_status'])) ?></span>
<?php endif; ?>
