<div class="grid-main">
  <section class="card">
    <h2>Clients (<?= count($customers) ?>)</h2>
    <table class="table">
      <thead><tr><th>Client</th><th>Commandes</th><th>Total dépensé</th><th>Dernière commande</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr><td><strong><?= e($c['name']) ?></strong><br><a class="small" href="<?= e(url('admin/commandes', ['q' => $c['email']])) ?>"><?= e($c['email']) ?></a></td><td><?= (int) $c['orders'] ?></td><td><?= e(money($c['spent'])) ?></td><td><?= e(date_fr($c['last_order'], false)) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="4" class="muted">Pas encore de clients.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
  <section class="card">
    <div class="card-head"><h2>Newsletter (<?= $subCount ?>)</h2><a class="btn" href="<?= e(url('admin/newsletter/export')) ?>">Export CSV</a></div>
    <p class="muted small"><?= config('brevo.api_key') ? 'Synchronisation Brevo active.' : 'Astuce : renseignez une clé API Brevo pour synchroniser automatiquement vos abonnés.' ?></p>
    <ul class="plain">
      <?php foreach ($subscribers as $s): ?><li><?= e($s['email']) ?> <small class="muted">· <?= e($s['source']) ?> · <?= e(date_fr($s['created_at'], false)) ?><?= $s['synced'] ? ' · Brevo ✓' : '' ?></small></li><?php endforeach; ?>
    </ul>
  </section>
</div>
