<?php use App\Core\View; $max = max(1, max($daily)); ?>
<?php $todo = array_filter($checklist, static fn ($c) => !$c[1]); ?>
<?php if ($todo): ?>
<section class="card checklist">
  <h2>Mise en route</h2>
  <ul>
    <?php foreach ($checklist as [$label, $ok, $link]): ?>
      <li class="<?= $ok ? 'ok' : 'ko' ?>"><?= $ok ? '✔' : '○' ?> <a href="<?= e(url($link)) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="kpis">
  <div class="kpi"><span>CA du mois</span><strong><?= e(money($stats['revenue_month'])) ?></strong><small><?= $stats['orders_month'] ?> commande(s)</small></div>
  <div class="kpi"><span>CA 30 jours</span><strong><?= e(money($stats['revenue_30'])) ?></strong><small><?= $stats['orders_30'] ?> commande(s)</small></div>
  <div class="kpi"><span>Panier moyen</span><strong><?= e(money($stats['aov'])) ?></strong><small>sur 30 jours</small></div>
  <div class="kpi"><span>Paniers abandonnés</span><strong><?= $stats['abandoned_30'] ?></strong><small>sur 30 jours</small></div>
  <div class="kpi"><span>Abonnés newsletter</span><strong><?= $stats['subscribers'] ?></strong><small><?= $stats['products'] ?> produits en ligne</small></div>
</section>

<section class="card">
  <h2>Ventes des 14 derniers jours</h2>
  <div class="chart" role="img" aria-label="Chiffre d'affaires quotidien">
    <?php foreach ($daily as $day => $amount): ?>
      <div class="bar" title="<?= e(date('d/m', strtotime($day)) . ' : ' . money($amount)) ?>">
        <span style="height: <?= max(2, (int) round($amount * 100 / $max)) ?>%"></span>
        <small><?= date('d', strtotime($day)) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="grid-2">
  <section class="card">
    <h2>À traiter <?php if ($attention): ?><span class="pill"><?= count($attention) ?></span><?php endif; ?></h2>
    <?php if (!$attention): ?><p class="muted">Rien à signaler, tout roule 🐎</p><?php endif; ?>
    <table class="table">
      <?php foreach ($attention as $order): ?>
        <tr><td><a href="<?= e(url('admin/commandes/' . $order['id'])) ?>"><?= e($order['number']) ?></a></td><td><?= View::capture('admin/_status', ['order' => $order]) ?></td><td class="muted small"><?= e(mb_strimwidth((string) $order['last_error'], 0, 70, '…')) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </section>
  <section class="card">
    <h2>Meilleures ventes (30 j)</h2>
    <?php if (!$topProducts): ?><p class="muted">Pas encore de ventes.</p><?php endif; ?>
    <table class="table">
      <?php foreach ($topProducts as $p): ?>
        <tr><td><?= e($p['product_name']) ?></td><td><?= (int) $p['qty'] ?> vendu(s)</td><td class="right"><?= e(money($p['revenue'])) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </section>
</div>

<section class="card">
  <h2>Dernières commandes</h2>
  <table class="table">
    <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Statut</th><th class="right">Total</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $order): ?>
      <tr><td><a href="<?= e(url('admin/commandes/' . $order['id'])) ?>"><?= e($order['number']) ?></a></td><td><?= e(date_fr($order['paid_at'] ?? $order['created_at'])) ?></td><td><?= e($order['customer_name'] ?: $order['email'] ?: '—') ?></td><td><?= View::capture('admin/_status', ['order' => $order]) ?></td><td class="right"><?= e(money($order['total'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="5" class="muted">Aucune commande pour le moment.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
