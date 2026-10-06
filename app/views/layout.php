<?php
$u = user();
$center = $center ?? current_center();
$centers = user_centers();
$r = (string)($_GET['r'] ?? (is_admin() ? 'admin' : 'dashboard'));
$active = fn(string ...$routes) => in_array($r, $routes, true) ? 'active' : '';
$cartN = ($u && $center) ? cart_count((int)$u['id'], (int)$center['id']) : 0;
$toReceiveN = $center ? (int)val("SELECT COUNT(*) FROM purchase_orders WHERE center_id = ? AND status IN ('commande','partiel')", [$center['id']]) : 0;
if (is_admin()) {
    $pendingN = (int)val("SELECT COUNT(*) FROM request_lines WHERE status = 'pending'");
    $toOrderN = (int)val("SELECT COUNT(*) FROM purchase_orders WHERE status = 'a_commander'");
    $usersN = (int)val("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $suggN = pending_suggestions_count();
}
$lowN = $center ? stock_low_count((int)$center['id']) : 0;
$notifN = $u ? unread_notifications((int)$u['id']) : 0;
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?><?= e(app_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/css/app.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><rect width='24' height='24' rx='6' fill='%236366f1'/><path d='M7 9h10l-1 8H8z' stroke='white' stroke-width='2' fill='none'/></svg>">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-logo"><?= icon('cart', 20) ?></div>
      <div><?= e(app_name()) ?><small>Achats &amp; approvisionnement</small></div>
    </div>

    <?php if ($centers): ?>
    <form class="center-switch" method="get">
      <label for="center-select">Centre / site</label>
      <input type="hidden" name="r" value="<?= e(str_starts_with($r, 'admin') || in_array($r, ['reception', 'product'], true) ? 'dashboard' : $r) ?>">
      <select id="center-select" name="c" onchange="this.form.submit()">
        <?php foreach ($centers as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $center && (int)$center['id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>

    <nav class="nav">
      <div class="nav-title">Mon centre</div>
      <a class="<?= $active('dashboard') ?>" href="<?= url('dashboard') ?>"><?= icon('home') ?> Tableau de bord</a>
      <a class="<?= $active('catalog', 'product') ?>" href="<?= url('catalog') ?>"><?= icon('search') ?> Catalogue</a>
      <a class="<?= $active('cart', 'suggest') ?>" href="<?= url('cart') ?>"><?= icon('cart') ?> Mon panier <span class="count soft" id="cart-count" <?= $cartN ? '' : 'style="display:none"' ?>><?= $cartN ?></span></a>
      <a class="<?= $active('requests') ?>" href="<?= url('requests') ?>"><?= icon('clipboard') ?> Suivi des demandes</a>
      <a class="<?= $active('receptions', 'reception') ?>" href="<?= url('receptions') ?>"><?= icon('package-check') ?> Réceptions <?php if ($toReceiveN): ?><span class="count"><?= $toReceiveN ?></span><?php endif; ?></a>
      <a class="<?= $active('stock', 'stock/history') ?>" href="<?= url('stock') ?>"><?= icon('layers') ?> Inventaire <?php if ($lowN): ?><span class="count" title="Articles sous le seuil d'alerte"><?= $lowN ?></span><?php endif; ?></a>

      <?php if (is_admin()): ?>
      <div class="nav-title">Service achats</div>
      <a class="<?= $active('admin') ?>" href="<?= url('admin') ?>"><?= icon('chart') ?> Pilotage</a>
      <a class="<?= $active('admin/requests') ?>" href="<?= url('admin/requests') ?>"><?= icon('inbox') ?> Demandes à traiter <?php if ($pendingN): ?><span class="count"><?= $pendingN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/orders', 'admin/order') ?>" href="<?= url('admin/orders', ['status' => 'open']) ?>"><?= icon('file') ?> Bons de commande <?php if ($toOrderN): ?><span class="count soft"><?= $toOrderN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/suggestions', 'admin/suggestion') ?>" href="<?= url('admin/suggestions') ?>"><?= icon('sparkles') ?> Articles proposés <?php if ($suggN): ?><span class="count"><?= $suggN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/suppliers', 'admin/supplier') ?>" href="<?= url('admin/suppliers') ?>"><?= icon('truck') ?> Fournisseurs</a>
      <a class="<?= $active('admin/products', 'admin/product', 'admin/products/import') ?>" href="<?= url('admin/products') ?>"><?= icon('box') ?> Articles</a>
      <a class="<?= $active('admin/categories') ?>" href="<?= url('admin/categories') ?>"><?= icon('tag') ?> Catégories</a>
      <a class="<?= $active('admin/deadlines') ?>" href="<?= url('admin/deadlines') ?>"><?= icon('calendar') ?> Dates limites</a>
      <a class="<?= $active('admin/budgets') ?>" href="<?= url('admin/budgets') ?>"><?= icon('wallet') ?> Budgets</a>
      <a class="<?= $active('admin/stocks') ?>" href="<?= url('admin/stocks') ?>"><?= icon('layers') ?> Stocks des centres</a>
      <div class="nav-title">Organisation</div>
      <a class="<?= $active('admin/centers', 'admin/center') ?>" href="<?= url('admin/centers') ?>"><?= icon('building') ?> Centres</a>
      <a class="<?= $active('admin/users', 'admin/user') ?>" href="<?= url('admin/users') ?>"><?= icon('users') ?> Comptes <?php if ($usersN): ?><span class="count"><?= $usersN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/settings') ?>" href="<?= url('admin/settings') ?>"><?= icon('settings') ?> Paramètres</a>
      <a class="<?= $active('admin/updates') ?>" href="<?= url('admin/updates') ?>"><?= icon('refresh') ?> Mises à jour <span class="count soft">v<?= e(APP_VERSION) ?></span></a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-foot">
      <div class="avatar"><?= e(initials($u['first_name'], $u['last_name'])) ?></div>
      <div style="flex:1;min-width:0">
        <a href="<?= url('profile') ?>" style="color:#fff;font-weight:600"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></a>
        <div style="font-size:.78rem;opacity:.7"><?= e($u['job'] ?: ($u['role'] === 'admin' ? 'Administrateur' : 'Salarié')) ?></div>
      </div>
      <a href="<?= url('logout') ?>" title="Se déconnecter"><?= icon('logout') ?></a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="btn btn-ghost btn-icon menu-btn" type="button" onclick="document.body.classList.toggle('nav-open')" aria-label="Menu"><?= icon('menu') ?></button>
      <?php if ($center): ?>
      <form class="topbar-search search-box compact" action="index.php" method="get" data-suggest>
        <input type="hidden" name="r" value="catalog">
        <span class="ic-left"><?= icon('search', 18) ?></span>
        <input type="search" name="q" placeholder="Rechercher un article… (ex : gants nitrile M, de quoi désinfecter)" autocomplete="off" value="<?= e($r === 'catalog' ? ($_GET['q'] ?? '') : '') ?>">
        <button type="button" class="btn btn-ghost btn-icon scan-btn" data-scan="search" title="Scanner un code-barres"><?= icon('barcode', 18) ?></button>
      </form>
      <button type="button" class="btn btn-ghost btn-icon scan-mobile" data-scan="search" title="Scanner un code-barres"><?= icon('camera') ?></button>
      <?php endif; ?>
      <div class="topbar-actions">
        <?php if ($center): ?>
          <span class="badge badge-violet" title="Centre courant"><span class="dot" style="background:<?= e($center['color']) ?>"></span><?= e($center['name']) ?></span>
        <?php endif; ?>
        <div class="bell-wrap">
          <a class="btn btn-ghost btn-icon bell" href="<?= url('notifications') ?>" title="Notifications" data-bell><?= icon('bell') ?><span class="bell-count" <?= $notifN ? '' : 'style="display:none"' ?>><?= $notifN ?></span></a>
        </div>
        <a class="btn btn-ghost btn-icon" href="<?= url('cart') ?>" title="Panier"><?= icon('cart') ?></a>
      </div>
    </header>

    <main class="content">
      <?php foreach (flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= icon($f['type'] === 'success' ? 'check-circle' : ($f['type'] === 'error' ? 'alert' : 'info')) ?><div><?= e($f['message']) ?></div></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<div class="toast-zone" id="toasts"></div>
<script>window.APP = { csrf: <?= json_encode(csrf_token()) ?>, showPrices: <?= show_prices() ? 'true' : 'false' ?> };</script>
<script src="assets/js/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
