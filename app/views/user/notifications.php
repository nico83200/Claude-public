<?php
$icons = ['request_new' => ['inbox', 'g-pink'], 'account_pending' => ['users', 'g-violet'], 'request_refused' => ['x', 'g-amber'],
    'po_created' => ['check', 'g-indigo'], 'po_ordered' => ['truck', 'g-blue'], 'po_received' => ['package-check', 'g-green'],
    'po_cancelled' => ['x', 'g-amber'], 'budget_alert' => ['wallet', 'g-pink'], 'stock_low' => ['alert', 'g-amber']];
?>
<div class="page-head">
  <div><h1>Notifications</h1><p>Suivi de vos commandes étape par étape. Réglez vos préférences dans <a href="<?= url('profile') ?>">Mon profil</a>.</p></div>
</div>
<div class="card">
  <?php if ($items): foreach ($items as $n): [$ic, $g] = $icons[$n['type']] ?? ['bell', 'g-indigo']; ?>
    <div class="notif <?= $n['read_at'] ? '' : 'unread' ?>">
      <div class="notif-ic <?= $g ?>"><?= icon($ic, 18) ?></div>
      <div style="flex:1;min-width:0">
        <div class="strong"><?= e($n['title']) ?></div>
        <?php if ($n['body']): ?><div><small><?= nl2br(e($n['body'])) ?></small></div><?php endif; ?>
        <small class="muted"><?= date_fr($n['created_at'], true) ?></small>
      </div>
      <?php if ($n['link']): ?><a class="btn btn-sm" href="<?= e($n['link']) ?>">Voir</a><?php endif; ?>
    </div>
  <?php endforeach; else: ?>
    <div class="empty"><?= icon('bell') ?><p>Aucune notification.</p></div>
  <?php endif; ?>
</div>
