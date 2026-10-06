<div class="page-head">
  <div><h1>Centres</h1><p>Chaque commande est rattachée à un seul centre. Les produits et fournisseurs sont partagés.</p></div>
  <a class="btn btn-primary" href="<?= url('admin/center') ?>"><?= icon('plus', 18) ?> Nouveau centre</a>
</div>
<div class="grid grid-3">
<?php foreach ($centers as $c): ?>
  <div class="card" style="overflow:hidden;<?= $c['active'] ? '' : 'opacity:.6' ?>">
    <div style="height:8px;background:<?= e($c['color']) ?>"></div>
    <div class="card-body">
      <div class="row"><div class="stat-icon" style="background:<?= e($c['color']) ?>;width:44px;height:44px"><?= icon('building', 22) ?></div>
        <div><h3 class="mb-0"><?= e($c['name']) ?> <?= $c['code'] ? '<small class="muted">' . e($c['code']) . '</small>' : '' ?></h3><small><?= e(trim(($c['address'] ?? '') . ' ' . ($c['city'] ?? ''))) ?: '—' ?></small></div>
      </div>
      <div class="chips mt-2">
        <span class="badge badge-violet"><?= icon('users', 13) ?> <?= (int)$c['nb_users'] ?> utilisateur(s)</span>
        <?php if ($c['nb_pending']): ?><span class="badge badge-amber"><?= (int)$c['nb_pending'] ?> demande(s)</span><?php endif; ?>
        <?php if ($c['nb_to_receive']): ?><span class="badge badge-blue"><?= (int)$c['nb_to_receive'] ?> livraison(s)</span><?php endif; ?>
        <?php if (!$c['active']): ?><span class="badge badge-gray">Inactif</span><?php endif; ?>
      </div>
    </div>
    <div class="card-foot row">
      <a class="btn btn-sm" href="<?= url('admin/center', ['id' => $c['id']]) ?>"><?= icon('edit', 15) ?> Modifier</a>
      <a class="btn btn-sm btn-ghost" href="<?= url('dashboard', ['c' => $c['id']]) ?>"><?= icon('eye', 15) ?> Voir le tableau de bord</a>
    </div>
  </div>
<?php endforeach; ?>
</div>
