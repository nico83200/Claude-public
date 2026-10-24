<?php use App\Core\View; ?>
<div class="toolbar">
  <nav class="tabs">
    <?php foreach (['' => 'Payées', 'attention' => 'À traiter', 'in_production' => 'En production', 'shipped' => 'Expédiées', 'refunded' => 'Remboursées', 'canceled' => 'Annulées', 'abandoned' => 'Paniers abandonnés'] as $k => $label): ?>
      <a href="<?= e(url('admin/commandes', array_filter(['statut' => $k, 'q' => $q]))) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <form method="get" class="inline">
    <?php if ($status): ?><input type="hidden" name="statut" value="<?= e($status) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="N°, client, e-mail…">
    <button class="btn">Rechercher</button>
    <a class="btn" href="<?= e(url('admin/commandes/export', array_filter(['statut' => $status, 'q' => $q]))) ?>">Export CSV</a>
  </form>
</div>
<section class="card">
  <?php if ($status === 'abandoned' && $total): ?>
    <form method="post" action="<?= e(url('admin/commandes/purger-abandonnees')) ?>" class="inline right" data-confirm="Supprimer définitivement les paniers abandonnés (jamais payés) de plus de 7 jours ?">
      <?= csrf_field() ?><input type="hidden" name="days" value="7"><button class="btn btn-danger">Supprimer les paniers abandonnés de plus de 7 jours</button>
    </form>
  <?php endif; ?>
  <p class="muted small"><?= $total ?> commande(s)</p>
  <table class="table">
    <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Articles</th><th>Statut</th><th>Printful</th><th class="right">Total</th><?php if ($status === 'abandoned'): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($orders as $order): ?>
      <tr>
        <td><a href="<?= e(url('admin/commandes/' . $order['id'])) ?>"><strong><?= e($order['number']) ?></strong></a></td>
        <td><?= e(date_fr($order['paid_at'] ?? $order['created_at'])) ?></td>
        <td><?= e($order['customer_name'] ?: '—') ?><br><small class="muted"><?= e($order['email']) ?></small></td>
        <td><?= (int) $order['item_count'] ?></td>
        <td><?= View::capture('admin/_status', ['order' => $order]) ?></td>
        <td class="small"><?= e(fulfillment_status_label($order['fulfillment_status'])) ?></td>
        <td class="right"><?= e(money($order['total'])) ?></td>
        <?php if ($status === 'abandoned'): ?>
          <td class="right"><?php if (App\Services\OrderService::canDelete($order)): ?><form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/supprimer')) ?>" data-confirm="Supprimer définitivement la commande <?= e($order['number']) ?> ?"><?= csrf_field() ?><button class="btn btn-sm btn-danger" title="Supprimer">Supprimer</button></form><?php endif; ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="7" class="muted">Aucune commande.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if ($pages > 1): ?>
    <nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e(url('admin/commandes', array_filter(['statut' => $status, 'q' => $q, 'page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?></nav>
  <?php endif; ?>
</section>
