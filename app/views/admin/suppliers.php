<div class="page-head">
  <div><h1>Fournisseurs</h1><p>Coordonnées, minimum de commande, franco de port et centres desservis.</p></div>
  <a class="btn btn-primary" href="<?= url('admin/supplier') ?>"><?= icon('plus', 18) ?> Nouveau fournisseur</a>
</div>
<div class="grid grid-3">
<?php foreach ($suppliers as $s): $pct = (float)$s['min_order_amount'] > 0 ? min(100, $s['pending_total'] / $s['min_order_amount'] * 100) : 0; ?>
  <div class="card" style="border-top:5px solid <?= e($s['color']) ?>;<?= $s['active'] ? '' : 'opacity:.6' ?>">
    <div class="card-body">
      <div class="row" style="align-items:flex-start">
        <div class="stat-icon" style="background:<?= e($s['color']) ?>;width:44px;height:44px"><?= icon('truck', 22) ?></div>
        <div style="flex:1;min-width:0">
          <h3 class="mb-0"><?= e($s['name']) ?></h3>
          <small><?= e($s['contact_name'] ?: 'Pas de contact') ?></small>
        </div>
        <?php if (!$s['active']): ?><span class="badge badge-gray">Inactif</span><?php endif; ?>
      </div>
      <div class="chips mt-2">
        <span class="badge badge-violet"><?= (int)$s['nb_products'] ?> articles</span>
        <span class="badge badge-amber">Min. <?= money($s['min_order_amount']) ?></span>
        <?php if ((float)$s['free_shipping_from'] > 0): ?><span class="badge badge-green">Franco <?= money($s['free_shipping_from']) ?></span><?php endif; ?>
        <?php if ($s['all_centers']): ?><span class="badge badge-blue">Tous les centres</span><?php else: ?><span class="badge badge-pink" title="<?= e(implode(', ', $restricted[(int)$s['id']] ?? [])) ?>"><?= icon('lock', 12) ?> <?= count($restricted[(int)$s['id']] ?? []) ?> centre(s)</span><?php endif; ?>
      </div>
      <?php if ($s['nb_pending']): ?>
        <div class="mt-2">
          <div class="row" style="justify-content:space-between;font-size:.82rem"><span><?= (int)$s['nb_pending'] ?> ligne(s) en attente</span><strong><?= money($s['pending_total']) ?></strong></div>
          <?php if ((float)$s['min_order_amount'] > 0): ?><div class="progress <?= $pct >= 100 ? 'ok' : 'warn' ?>"><span style="width:<?= $pct ?>%"></span></div><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="card-foot row">
      <a class="btn btn-sm" href="<?= url('admin/supplier', ['id' => $s['id']]) ?>"><?= icon('edit', 15) ?> Modifier</a>
      <a class="btn btn-sm btn-ghost" href="<?= url('admin/products', ['sup' => $s['id']]) ?>"><?= icon('box', 15) ?> Articles</a>
      <span class="spacer"></span>
      <?php if ($s['email']): ?><a class="btn btn-sm btn-ghost btn-icon" href="mailto:<?= e($s['email']) ?>" title="<?= e($s['email']) ?>"><?= icon('mail', 16) ?></a><?php endif; ?>
      <?php if ($s['phone']): ?><a class="btn btn-sm btn-ghost btn-icon" href="tel:<?= e($s['phone']) ?>" title="<?= e($s['phone']) ?>"><?= icon('phone', 16) ?></a><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php if (!$suppliers): ?><div class="card"><div class="empty"><?= icon('truck') ?><p>Aucun fournisseur. Commencez par en créer un.</p></div></div><?php endif; ?>
