<?php
$cur = $d['cur'];
$fmtDelta = fn(?float $v, bool $upIsGood = false) => $v === null ? '<span class="muted">—</span>'
    : '<span class="delta ' . (($v > 0) === $upIsGood ? 'good' : ($v == 0 ? '' : 'bad')) . '">' . ($v > 0 ? '▲ +' : ($v < 0 ? '▼ ' : '')) . str_replace('.', ',', (string)$v) . ' %</span>';
/** Barres horizontales : une seule couleur, valeur écrite à côté, pas de légende (une seule série). */
$bars = function (array $rows, int $limit = 8, bool $withDot = false) {
    $rows = array_slice($rows, 0, $limit);
    if (!$rows) {
        echo '<div class="empty"><p>Aucune dépense sur la période.</p></div>';
        return;
    }
    $max = max(array_column($rows, 'amount')) ?: 1;
    echo '<div class="hbars">';
    foreach ($rows as $r) {
        $pct = $r['amount'] / $max * 100;
        echo '<div class="hbar-row" title="' . e($r['name']) . ' : ' . e(money($r['amount'])) . ($r['savings'] > 0.005 ? ' · économies ' . e(money($r['savings'])) : '') . '">'
            . '<div class="hbar-label">' . ($withDot && $r['color'] ? '<span class="dot" style="background:' . e($r['color']) . '"></span> ' : '') . e($r['name']) . '</div>'
            . '<div class="hbar-track"><span style="width:' . max(0.6, $pct) . '%"></span></div>'
            . '<div class="hbar-value">' . money($r['amount']) . ($r['savings'] > 0.005 ? '<small>économie ' . money($r['savings']) . '</small>' : '') . '</div></div>';
    }
    echo '</div>';
};
$maxMonth = max(1, ...array_column($d['monthly'], 'amount'));
?>
<div class="page-head">
  <div><h1>Tableau de bord direction</h1><p><?= e($d['range']['label']) ?> · du <?= date_fr($d['range']['from']) ?> au <?= date_fr(date('Y-m-d', strtotime($d['range']['to'] . ' -1 day'))) ?> · dépenses engagées (bons commandés), frais de port inclus.</p></div>
  <form method="get" class="row filters-row">
    <input type="hidden" name="r" value="admin/direction">
    <select name="period" onchange="this.form.submit()" style="width:auto"><?php foreach (REPORT_PERIODS as $k => $l): ?><option value="<?= $k ?>" <?= $period === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select name="center" onchange="this.form.submit()" style="width:auto"><option value="">Tous les centres</option><?php foreach ($centers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $center === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <a class="btn" href="<?= url('admin/direction/pdf', ['month' => date('Y-m', strtotime('first day of last month'))]) ?>" target="_blank"><?= icon('download', 16) ?> Rapport PDF du mois dernier</a>
  </form>
</div>

<div class="grid grid-4 mb-2">
  <div class="stat"><div><div class="stat-label">Dépenses</div><div class="stat-value"><?= money($cur['spent']) ?></div><small><?= $fmtDelta($d['delta']['spent']) ?> vs période précédente</small></div></div>
  <div class="stat"><div><div class="stat-label">Économies négociées</div><div class="stat-value" style="color:#047857"><?= money($cur['savings']) ?></div><small><?= str_replace('.', ',', (string)$cur['savings_pct']) ?> % du prix catalogue</small></div></div>
  <div class="stat"><div><div class="stat-label">Bons de commande</div><div class="stat-value"><?= (int)$cur['orders'] ?></div><small><?= $cur['orders'] ? 'panier moyen ' . money($cur['spent'] / $cur['orders']) : '—' ?></small></div></div>
  <div class="stat"><div><div class="stat-label">Valeur des stocks</div><div class="stat-value"><?= money($d['stock_value']) ?></div><small>articles suivis, prix actuels</small></div></div>
</div>

<div class="card mb-2">
  <div class="card-head"><h2><?= icon('chart') ?> Dépenses mensuelles · 12 mois</h2><small class="muted">max <?= money($maxMonth) ?></small></div>
  <div class="card-body">
    <div class="mchart" role="img" aria-label="Dépenses mensuelles sur 12 mois">
      <?php foreach ($d['monthly'] as $i => $m): $h = $m['amount'] / $maxMonth * 100; $isLast = $i === count($d['monthly']) - 1; ?>
        <div class="mcol <?= $isLast ? 'cur' : '' ?>" tabindex="0">
          <div class="mtip"><?= e(ucfirst(month_fr($m['month']))) ?><strong><?= money($m['amount']) ?></strong></div>
          <div class="mbar-wrap"><span class="mbar" style="height:<?= max(0.8, $h) ?>%"></span></div>
          <div class="mlabel"><?= e(month_fr($m['month'], true)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <details class="mt-1"><summary class="muted" style="font-size:.85rem;cursor:pointer">Voir les montants</summary>
      <table class="table mt-1"><tbody><?php foreach ($d['monthly'] as $m): ?><tr><td><?= e(ucfirst(month_fr($m['month']))) ?></td><td class="num"><?= money($m['amount']) ?></td></tr><?php endforeach; ?></tbody></table></details>
  </div>
</div>

<div class="grid grid-2 mb-2">
  <div class="card"><div class="card-head"><h2><?= icon('building') ?> Par centre</h2></div><div class="card-body"><?php $bars($cur['centers'], 10, true); ?></div></div>
  <div class="card"><div class="card-head"><h2><?= icon('tag') ?> Par catégorie</h2></div><div class="card-body"><?php $bars($cur['categories']); ?></div></div>
  <div class="card"><div class="card-head"><h2><?= icon('truck') ?> Par fournisseur</h2></div><div class="card-body"><?php $bars($cur['suppliers']); ?></div></div>
  <div class="card"><div class="card-head"><h2><?= icon('box') ?> Articles les plus achetés</h2></div><div class="card-body"><?php $bars($cur['products'], 10); ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2><?= icon('wallet') ?> Budgets <?= date('Y') ?></h2></div>
    <div class="card-body">
      <?php if ($d['budgets']): foreach ($d['budgets'] as $b): $lvl = $b['pct'] >= 100 ? 'red' : ($b['pct'] >= 80 ? 'amber' : 'green'); ?>
        <div class="hbar-row"><div class="hbar-label"><span class="dot" style="background:<?= e($b['color']) ?>"></span> <?= e($b['name']) ?></div>
          <div class="hbar-track budget"><span class="lvl-<?= $lvl ?>" style="width:<?= min(100, max(0.6, $b['pct'])) ?>%"></span></div>
          <div class="hbar-value"><?= $b['pct'] ?> %<small><?= money($b['spent']) ?> / <?= money($b['amount']) ?></small></div></div>
      <?php endforeach; else: ?><div class="empty"><p>Aucun budget défini. <a href="<?= url('admin/budgets') ?>">Définir les budgets</a></p></div><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('file') ?> Rapports mensuels</h2></div>
    <div class="card-body">
      <p class="muted" style="margin-top:0;font-size:.9rem">Un rapport PDF est créé automatiquement chaque début de mois (dépenses, économies, répartitions, évolution, budgets).</p>
      <form method="post" class="row mb-1"><?= csrf_field() ?>
        <input type="text" name="report_emails" value="<?= e((string)setting('report_emails', '')) ?>" placeholder="E-mails de la direction (séparés par des virgules)" style="flex:1;min-width:220px">
        <button class="btn btn-sm" type="submit"><?= icon('mail', 15) ?> Envoyer chaque mois</button>
      </form>
      <?php if (setting('mail_enabled', '0') !== '1'): ?><small class="muted">L'envoi d'e-mails est désactivé dans les paramètres : les rapports restent téléchargeables ci-dessous.</small><?php endif; ?>
      <ul class="list mt-1"><?php foreach (array_slice($reports, 0, 12) as $r): ?>
        <li><div class="grow"><?= e(ucfirst(month_fr($r['month']))) ?></div><a class="btn btn-sm" href="<?= url('admin/direction/pdf', ['month' => $r['month']]) ?>" target="_blank"><?= icon('download', 14) ?> PDF</a></li>
      <?php endforeach; ?><?php if (!$reports): ?><li class="muted">Le premier rapport sera créé au début du mois prochain (ou téléchargez celui du mois dernier ci-dessus).</li><?php endif; ?></ul>
    </div>
  </div>
</div>
