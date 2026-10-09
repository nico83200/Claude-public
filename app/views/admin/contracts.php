<div class="page-head">
  <div><h1>Contrats et marchés</h1><p>Prix contractuels négociés en direct ou via un groupement d'achats : appliqués automatiquement aux articles pendant la durée du contrat, avec une alerte avant l'échéance.</p></div>
  <a class="btn btn-primary" href="<?= url('admin/contract') ?>"><?= icon('plus', 18) ?> Nouveau contrat</a>
</div>

<div class="grid grid-4 mb-2">
  <a class="stat c-green" href="<?= url('admin/contracts', ['f' => 'active']) ?>"><div class="stat-icon g-green"><?= icon('check-circle', 24) ?></div><div><div class="stat-value"><?= (int)($counts['active'] ?? 0) ?></div><div class="stat-label">Contrats en cours</div></div></a>
  <a class="stat c-amber" href="<?= url('admin/contracts', ['f' => 'renew']) ?>"><div class="stat-icon g-amber"><?= icon('clock', 24) ?></div><div><div class="stat-value"><?= (int)($counts['renew'] ?? 0) ?></div><div class="stat-label">À renouveler (préavis atteint)</div></div></a>
  <div class="stat c-blue"><div class="stat-icon g-blue"><?= icon('box', 24) ?></div><div><div class="stat-value"><?= (int)$covered ?></div><div class="stat-label">Articles à prix contractuel</div></div></div>
  <div class="stat c-violet"><div class="stat-icon g-violet"><?= icon('euro', 24) ?></div><div><div class="stat-value"><?= money($annual) ?></div><div class="stat-label">Montant annuel estimé des contrats en cours</div></div></div>
</div>

<div class="tabs">
  <?php foreach (['' => 'Tous', 'active' => 'En cours', 'renew' => 'À renouveler', 'upcoming' => 'À venir', 'expired' => 'Expirés'] as $k => $l): ?>
    <a class="<?= $filter === $k ? 'active' : '' ?>" href="<?= url('admin/contracts', ['f' => $k ?: null]) ?>"><?= $l ?><?php if ($k && !empty($counts[$k])): ?> <span class="badge <?= $k === 'renew' ? 'badge-amber' : 'badge-gray' ?>"><?= (int)$counts[$k] ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$all): ?>
  <div class="card"><div class="empty"><?= icon('file') ?><h3>Aucun contrat pour l'instant</h3>
    <p>Enregistrez vos marchés (UniHA, Resah, UGAP…) et vos contrats directs : leurs prix s'appliquent aux articles, et vous êtes prévenu avant chaque échéance.</p>
    <a class="btn btn-primary" href="<?= url('admin/contract') ?>"><?= icon('plus', 18) ?> Enregistrer un premier contrat</a></div></div>
<?php elseif (!$contracts): ?>
  <div class="card"><div class="empty"><?= icon('file') ?><p>Aucun contrat dans cette catégorie.</p></div></div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Contrat</th><th>Fournisseur</th><th>Période</th><th>Avancement</th><th class="num">Articles</th><th>État</th></tr></thead>
      <tbody>
      <?php foreach ($contracts as $c): $st = $c['status'];
        $total = max(1, strtotime($c['end_date']) - strtotime($c['start_date']));
        $pct = max(0, min(100, (time() - strtotime($c['start_date'])) / $total * 100)); ?>
        <tr>
          <td><a class="strong" href="<?= url('admin/contract', ['id' => $c['id']]) ?>"><?= e($c['name']) ?></a>
            <div class="chips"><?php if ($c['buying_group']): ?><span class="badge badge-violet"><?= e($c['buying_group']) ?></span><?php else: ?><span class="badge badge-gray">Contrat direct</span><?php endif; ?><?php if ($c['reference']): ?><small class="muted">n° <?= e($c['reference']) ?></small><?php endif; ?></div></td>
          <td><span class="dot" style="background:<?= e($c['supplier_color']) ?>"></span> <?= e($c['supplier_name']) ?></td>
          <td><small><?= e(date_fr($c['start_date'])) ?> → <?= e(date_fr($c['end_date'])) ?></small><?php if ((int)$c['tacit_renewal']): ?><br><small class="muted">reconduction tacite</small><?php endif; ?></td>
          <td style="min-width:140px"><div class="progress <?= $st['key'] === 'renew' ? 'warn' : ($st['key'] === 'active' ? 'ok' : '') ?>"><span style="width:<?= round($pct) ?>%"></span></div><small class="muted"><?= e($st['key'] === 'upcoming' ? 'commence le ' . date_fr($c['start_date']) : contract_days_label($st['days'])) ?></small></td>
          <td class="num"><?= (int)$c['n_prices'] ?></td>
          <td><?= badge($st) ?><?php if ($st['key'] === 'renew'): ?><br><small class="muted">décision avant le <?= e(date_fr(contract_decision_date($c))) ?></small><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
