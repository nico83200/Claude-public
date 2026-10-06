<?php
$u = user();
$hour = (int)date('G');
$hello = $hour < 18 ? 'Bonjour' : 'Bonsoir';
$urgentDeadline = null;
foreach ($deadlines as $d) {
    $cd = countdown($d['deadline_at']);
    if (in_array($cd['level'], ['danger', 'warning'], true)) { $urgentDeadline = $d + ['cd' => $cd]; break; }
}
?>
<section class="hero mb-2">
  <span class="hero-tag"><?= icon('building', 15) ?> <?= e($center['name']) ?> · <?= e(date_long_fr(date('Y-m-d'))) ?></span>
  <h1><?= $hello ?> <?= e($u['first_name']) ?> 👋</h1>
  <p><?= e(setting('welcome_message') ?: 'De quoi avez-vous besoin aujourd\'hui ? Décrivez-le avec vos mots, l\'assistant trouve le bon article.') ?></p>
  <form class="hero-search search-box" action="index.php" method="get" data-suggest>
    <input type="hidden" name="r" value="catalog">
    <span class="ic-left"><?= icon('search', 20) ?></span>
    <input type="search" name="q" placeholder="Ex : gants taille M, papier pour table d'examen, bande de strapping…" autocomplete="off">
    <button class="btn btn-primary" type="submit"><?= icon('sparkles', 16) ?> Rechercher</button>
  </form>
</section>

<?php if ($urgentDeadline): ?>
<div class="alert-deadline">
  <?= icon('clock', 26) ?>
  <div style="flex:1">
    <strong><?= e($urgentDeadline['title']) ?></strong> — date limite le <?= date_fr($urgentDeadline['deadline_at'], true) ?>
    <?php if ($urgentDeadline['supplier_name']): ?> (<?= e($urgentDeadline['supplier_name']) ?>)<?php endif; ?>
  </div>
  <span class="countdown <?= e($urgentDeadline['cd']['level']) ?>"><?= e($urgentDeadline['cd']['label']) ?></span>
</div>
<?php endif; ?>

<div class="grid grid-4 mb-2">
  <a class="stat c-indigo" href="<?= url('cart') ?>">
    <div class="stat-icon g-indigo"><?= icon('cart', 24) ?></div>
    <div><div class="stat-value"><?= $cartCount ?></div><div class="stat-label">Article(s) dans mon panier</div></div>
  </a>
  <a class="stat c-amber" href="<?= url('requests', ['status' => 'pending']) ?>">
    <div class="stat-icon g-amber"><?= icon('clock', 24) ?></div>
    <div><div class="stat-value"><?= $myPending ?></div><div class="stat-label">Mes demandes en attente</div></div>
  </a>
  <a class="stat c-blue" href="<?= url('requests', ['scope' => 'center']) ?>">
    <div class="stat-icon g-blue"><?= icon('clipboard', 24) ?></div>
    <div><div class="stat-value"><?= $centerPending + $inProgress ?></div><div class="stat-label">En cours pour le centre</div></div>
  </a>
  <a class="stat c-green" href="<?= url('receptions') ?>">
    <div class="stat-icon g-green"><?= icon('package-check', 24) ?></div>
    <div><div class="stat-value"><?= count($toReceive) ?></div><div class="stat-label">Livraison(s) à réceptionner</div></div>
  </a>
</div>

<div class="grid grid-main">
  <div class="stack">
    <?php if ($toReceive): ?>
    <div class="card">
      <div class="card-head"><h2><?= icon('truck') ?> Livraisons attendues</h2><a href="<?= url('receptions') ?>" class="btn btn-sm">Tout voir</a></div>
      <ul class="list">
        <?php foreach ($toReceive as $po): $t = po_totals((int)$po['id']); ?>
        <li>
          <span class="dot" style="background:<?= e($po['supplier_color']) ?>;width:12px;height:12px"></span>
          <div class="grow">
            <div class="title"><?= e($po['supplier_name']) ?> <small class="muted">· <?= e($po['po_number']) ?></small></div>
            <small>Commandé le <?= date_fr($po['ordered_at']) ?><?= $po['expected_date'] ? ' · livraison prévue le ' . date_fr($po['expected_date']) : '' ?> · <?= $t['received'] ?>/<?= $t['qty'] ?> reçus</small>
          </div>
          <?= po_status_badge($po['status']) ?>
          <a class="btn btn-success btn-sm" href="<?= url('reception', ['id' => $po['id']]) ?>"><?= icon('check', 16) ?> Réceptionner</a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h2><?= icon('star') ?> Mes favoris &amp; articles fréquents</h2><a class="btn btn-sm" href="<?= url('catalog') ?>">Catalogue</a></div>
      <div class="card-body">
        <?php if ($quick): ?>
          <div class="products" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
            <?php foreach (array_slice($quick, 0, 8) as $p) { partial('product_card', ['p' => $p, 'favIds' => $favIds, 'deadlinesBySupplier' => []]); } ?>
          </div>
        <?php else: ?>
          <div class="empty"><?= icon('heart') ?><p>Ajoutez des articles en favori (♥) pour les retrouver ici en un clic.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= icon('clipboard') ?> Dernières demandes du centre</h2><a class="btn btn-sm" href="<?= url('requests', ['scope' => 'center']) ?>">Tout le suivi</a></div>
      <?php if ($recent): ?>
      <ul class="list">
        <?php foreach ($recent as $r): ?>
        <li>
          <div class="avatar sm"><?= e(initials($r['first_name'], $r['last_name'])) ?></div>
          <div class="grow">
            <div class="title">Demande n°<?= (int)$r['id'] ?> — <?= e($r['first_name'] . ' ' . $r['last_name']) ?> <?= $r['urgent'] ? '<span class="badge badge-red">Urgent</span>' : '' ?></div>
            <small><?= date_fr($r['created_at'], true) ?> · <?= plural((int)$r['nb_lines'], 'article', 'articles') ?><?= show_prices() ? ' · ' . money($r['total']) : '' ?></small>
          </div>
          <div class="chips" style="justify-content:flex-end">
            <?php
            $counts = [];
            foreach ($r['lines'] as $l) { $s = request_line_status($l); $counts[$s['label']] = ($counts[$s['label']] ?? ['n' => 0, 's' => $s]); $counts[$s['label']]['n']++; }
            foreach ($counts as $c) { echo badge(['label' => $c['n'] . ' ' . mb_strtolower($c['s']['label']), 'color' => $c['s']['color']]); }
            ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <div class="empty"><?= icon('inbox') ?><p>Aucune demande pour l'instant.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('calendar') ?> Dates limites de commande</h2></div>
      <?php if ($deadlines): foreach ($deadlines as $d): $cd = countdown($d['deadline_at']); ?>
        <div class="deadline">
          <div class="deadline-date lvl-<?= e($cd['level']) ?>"><b><?= date('d', strtotime($d['deadline_at'])) ?></b><span><?= e(month_short_fr((int)date('n', strtotime($d['deadline_at'])))) ?></span></div>
          <div style="flex:1;min-width:0">
            <div class="strong"><?= e($d['title']) ?></div>
            <small><?= $d['supplier_name'] ? e($d['supplier_name']) . ' · ' : '' ?>avant <?= date('H\hi', strtotime($d['deadline_at'])) ?></small>
            <?php if ($d['description']): ?><div><small><?= e($d['description']) ?></small></div><?php endif; ?>
          </div>
          <span class="countdown <?= e($cd['level']) ?>"><?= e($cd['label']) ?></span>
        </div>
      <?php endforeach; else: ?>
        <div class="empty"><?= icon('calendar') ?><p>Aucune date limite à venir.</p></div>
      <?php endif; ?>
    </div>

    <?php $lowN = stock_low_count((int)$center['id']); if ($lowN): ?>
    <a class="stat c-pink" href="<?= url('stock', ['filter' => 'low']) ?>">
      <div class="stat-icon g-pink"><?= icon('layers', 24) ?></div>
      <div><div class="stat-value"><?= $lowN ?></div><div class="stat-label">Article(s) en stock bas — à recommander</div></div>
    </a>
    <?php endif; ?>
    <?php if (show_prices()): $b = budget_status((int)$center['id']); ?>
    <div class="card card-body">
      <h3><?= icon('wallet', 18) ?> Budget <?= date('Y') ?></h3>
      <?php partial('budget_gauge', ['b' => $b]); ?>
    </div>
    <?php endif; ?>
    <?php if (show_prices()): ?>
    <div class="stat c-violet">
      <div class="stat-icon g-violet"><?= icon('euro', 24) ?></div>
      <div><div class="stat-value"><?= money($monthSpend) ?></div><div class="stat-label">Commandé ce mois-ci pour le centre (HT)</div></div>
    </div>
    <?php endif; ?>

    <div class="card card-body">
      <h3><?= icon('info', 18) ?> Comment ça marche ?</h3>
      <ol style="margin:.5rem 0 0;padding-left:1.2rem;color:var(--muted)">
        <li>Recherchez vos articles et ajoutez-les au panier.</li>
        <li>Validez votre demande : elle part au service achats.</li>
        <li>Les achats regroupent les demandes par fournisseur et passent commande.</li>
        <li>À la livraison, cochez ce qui a été reçu dans « Réceptions ».</li>
      </ol>
    </div>
  </div>
</div>
