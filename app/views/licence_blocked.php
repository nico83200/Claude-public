<?php $c = support_contact(); ?>
<div>
  <div class="card-body" style="text-align:center">
    <div style="color:var(--red)"><?= icon('alert', 40) ?></div>
    <h1 style="font-size:1.35rem">Accès temporairement suspendu</h1>
    <p class="muted">L'accès à <?= e(app_name()) ?> est suspendu par <?= e($c['editor']) ?>, l'éditeur du logiciel. Vos données sont conservées.</p>
    <?php if (is_admin() && $notice): ?><div class="flash flash-error" style="text-align:left"><?= icon('info') ?><div><?= e($notice['text']) ?></div></div><?php endif; ?>
    <p><?= is_admin() ? 'Pour rétablir l\'accès, contactez' : 'Prévenez le service achats ou contactez' ?> <?= e($c['editor']) ?> : <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?></p>
    <div class="row" style="justify-content:center;flex-wrap:wrap">
      <?php if (is_admin()): ?><a class="btn btn-primary" href="<?= url('admin/settings') ?>#assistance"><?= icon('settings', 16) ?> Licence et assistance</a><?php endif; ?>
      <a class="btn" href="<?= url('support') ?>"><?= icon('info', 16) ?> Assistance</a>
      <a class="btn btn-ghost" href="<?= url('logout') ?>"><?= icon('logout', 16) ?> Se déconnecter</a>
    </div>
  </div>
</div>
