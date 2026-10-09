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
    $usersN = is_superadmin() ? (int)val("SELECT COUNT(*) FROM users WHERE status = 'pending'") : 0;
    $suggN = pending_suggestions_count();
    $contractN = count(array_filter(contracts_attention(), fn($c) => $c['status']['key'] === 'renew'));
}
$lowN = $center ? stock_low_count((int)$center['id']) : 0;
$approvalN = approvals_pending_count();
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
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#2a1fc4">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(app_name()) ?>">
<link rel="apple-touch-icon" href="assets/icons/icon-192.png">
<link rel="icon" type="image/svg+xml" href="assets/brand/centriva-mark.svg">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <?php if ($logo = brand_logo_url()): ?>
    <a class="brand brand-with-logo" href="index.php">
      <span class="brand-logo-box"><img src="<?= e($logo) ?>" alt="<?= e(setting('company_name') ?: app_name()) ?>"></span>
      <span class="brand-sub"><img src="assets/brand/centriva-mark.svg" alt="" width="16" height="16"> <?= e(app_name()) ?></span>
    </a>
    <?php else: ?>
    <div class="brand">
      <img class="brand-mark" src="assets/brand/centriva-mark.svg" alt="" width="40" height="40">
      <div><?= e(app_name()) ?><small>Achats &amp; approvisionnement</small></div>
    </div>
    <?php endif; ?>

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
      <a class="<?= $active('kits', 'kit') ?>" href="<?= url('kits') ?>"><?= icon('star') ?> Listes types</a>
      <a class="<?= $active('requests') ?>" href="<?= url('requests') ?>"><?= icon('clipboard') ?> Suivi des demandes</a>
      <a class="<?= $active('receptions', 'reception') ?>" href="<?= url('receptions') ?>"><?= icon('package-check') ?> Réceptions <?php if ($toReceiveN): ?><span class="count"><?= $toReceiveN ?></span><?php endif; ?></a>
      <?php if (is_manager() || (is_admin() && $approvalN)): ?>
      <a class="<?= $active('approvals') ?>" href="<?= url('approvals') ?>"><?= icon('check-circle') ?> Validations <?php if ($approvalN): ?><span class="count"><?= $approvalN ?></span><?php endif; ?></a>
      <?php endif; ?>
      <a class="<?= $active('stock', 'stock/history') ?>" href="<?= url('stock') ?>"><?= icon('layers') ?> Inventaire <?php if ($lowN): ?><span class="count" title="Articles sous le seuil d'alerte"><?= $lowN ?></span><?php endif; ?></a>

      <?php if (is_admin()): ?>
      <div class="nav-title">Service achats</div>
      <a class="<?= $active('admin') ?>" href="<?= url('admin') ?>"><?= icon('chart') ?> Pilotage</a>
      <a class="<?= $active('admin/direction') ?>" href="<?= url('admin/direction') ?>"><?= icon('euro') ?> Direction</a>
      <a class="<?= $active('admin/requests') ?>" href="<?= url('admin/requests') ?>"><?= icon('inbox') ?> Demandes à traiter <?php if ($pendingN): ?><span class="count"><?= $pendingN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/orders', 'admin/order') ?>" href="<?= url('admin/orders', ['status' => 'open']) ?>"><?= icon('file') ?> Bons de commande <?php if ($toOrderN): ?><span class="count soft"><?= $toOrderN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/suggestions', 'admin/suggestion') ?>" href="<?= url('admin/suggestions') ?>"><?= icon('sparkles') ?> Articles proposés <?php if ($suggN): ?><span class="count"><?= $suggN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/suppliers', 'admin/supplier') ?>" href="<?= url('admin/suppliers') ?>"><?= icon('truck') ?> Fournisseurs</a>
      <a class="<?= $active('admin/products', 'admin/product', 'admin/products/import') ?>" href="<?= url('admin/products') ?>"><?= icon('box') ?> Articles</a>
      <a class="<?= $active('admin/categories') ?>" href="<?= url('admin/categories') ?>"><?= icon('tag') ?> Catégories</a>
      <a class="<?= $active('admin/contracts', 'admin/contract') ?>" href="<?= url('admin/contracts') ?>"><?= icon('file') ?> Contrats et marchés <?php if ($contractN): ?><span class="count"><?= $contractN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/deadlines') ?>" href="<?= url('admin/deadlines') ?>"><?= icon('calendar') ?> Dates limites</a>
      <a class="<?= $active('admin/compare') ?>" href="<?= url('admin/compare') ?>"><?= icon('layers') ?> Comparateur</a>
      <a class="<?= $active('admin/invoices') ?>" href="<?= url('admin/invoices') ?>"><?= icon('euro') ?> Factures</a>
      <a class="<?= $active('admin/exports') ?>" href="<?= url('admin/exports') ?>"><?= icon('download') ?> Exports comptables</a>
      <a class="<?= $active('admin/budgets') ?>" href="<?= url('admin/budgets') ?>"><?= icon('wallet') ?> Budgets</a>
      <a class="<?= $active('admin/stocks') ?>" href="<?= url('admin/stocks') ?>"><?= icon('layers') ?> Stocks des centres</a>
      <?php if (is_superadmin()): ?>
      <div class="nav-title">Organisation</div>
      <a class="<?= $active('admin/centers', 'admin/center') ?>" href="<?= url('admin/centers') ?>"><?= icon('building') ?> Centres</a>
      <a class="<?= $active('admin/users', 'admin/user') ?>" href="<?= url('admin/users') ?>"><?= icon('users') ?> Comptes <?php if ($usersN): ?><span class="count"><?= $usersN ?></span><?php endif; ?></a>
      <a class="<?= $active('admin/settings') ?>" href="<?= url('admin/settings') ?>"><?= icon('settings') ?> Paramètres</a>
      <a class="<?= $active('admin/audit') ?>" href="<?= url('admin/audit') ?>"><?= icon('shield') ?> Journal d'audit</a>
      <a class="<?= $active('admin/transfer') ?>" href="<?= url('admin/transfer') ?>"><?= icon('refresh') ?> Export et import</a>
      <a class="<?= $active('admin/cleanup') ?>" href="<?= url('admin/cleanup') ?>"><?= icon('trash') ?> Nettoyage des données<?php if (demo_present()): ?> <span class="count">démo</span><?php endif; ?></a>
      <?php if (!current_instance()): ?><a class="<?= $active('admin/updates') ?>" href="<?= url('admin/updates') ?>"><?= icon('refresh') ?> Mises à jour <span class="count soft">v<?= e(APP_VERSION) ?></span></a><?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>
      <div class="nav-title">Aide</div>
      <a class="<?= $active('videos', 'admin/videos') ?>" href="<?= url('videos') ?>"><?= icon('play') ?> Tutoriels vidéo</a>
      <a class="<?= $active('support') ?>" href="<?= url('support') ?>"><?= icon('info') ?> Assistance</a>
    </nav>
    <a class="nav-editor" href="<?= e(support_contact()['site']) ?>" target="_blank" rel="noopener">Centriva · créé et maintenu par <strong><?= e(support_contact()['editor']) ?></strong></a>

    <div class="sidebar-foot">
      <div class="avatar"><?= e(initials($u['first_name'], $u['last_name'])) ?></div>
      <div style="flex:1;min-width:0">
        <a href="<?= url('profile') ?>" style="color:#fff;font-weight:600"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></a>
        <div style="font-size:.78rem;opacity:.7"><?= e($u['job'] ?: role_label($u['role'])) ?></div>
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
        <button type="button" class="btn btn-ghost btn-sm hidden" data-install title="Installer l'application sur cet appareil"><?= icon('download', 16) ?> Installer</button>
        <div class="bell-wrap">
          <a class="btn btn-ghost btn-icon bell" href="<?= url('notifications') ?>" title="Notifications" data-bell><?= icon('bell') ?><span class="bell-count" <?= $notifN ? '' : 'style="display:none"' ?>><?= $notifN ?></span></a>
        </div>
        <a class="btn btn-ghost btn-icon" href="<?= url('cart') ?>" title="Panier"><?= icon('cart') ?></a>
      </div>
    </header>

    <main class="content">
      <?php if (demo_mode()): ?>
        <div class="demo-bar"><?= icon('sparkles', 18) ?><div><strong>Démo Centriva</strong> · vous êtes <strong><?= e(mb_strtolower(role_label($u['role']))) ?></strong> (<?= e($u['first_name']) ?>). Données fictives, remises à zéro chaque nuit.</div>
          <a class="btn btn-sm" href="<?= url('logout') ?>">Changer de profil</a><a class="btn btn-sm btn-primary" href="<?= e(support_contact()['site']) ?>" target="_blank" rel="noopener">Obtenir Centriva</a></div>
      <?php endif; ?>
      <?php if (is_superadmin() && ($ln = licence_notice())): ?>
        <div class="flash flash-<?= $ln['level'] === 'danger' ? 'error' : 'info' ?> licence-notice"><?= icon($ln['level'] === 'info' ? 'info' : 'alert') ?><div><?= e($ln['text']) ?> <a href="<?= url('admin/settings') ?>#assistance">Détails</a><?php if (!empty($ln['pay_url'])): ?> <a class="btn btn-sm btn-primary" href="<?= e($ln['pay_url']) ?>" target="_blank" rel="noopener">Payer en ligne</a><?php endif; ?></div></div>
      <?php endif; ?>
      <?php if (is_superadmin() && !current_instance() && ($up = licence_update_available()) && licence_updates_allowed() && ($r ?? '') !== 'admin/updates'): ?>
        <div class="flash flash-info"><?= icon('sparkles') ?><div>Nouvelle version <strong><?= e($up['version']) ?></strong> disponible. <a href="<?= url('admin/updates') ?>">Voir les nouveautés et l'installer</a></div></div>
      <?php endif; ?>
      <?php foreach (flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= icon($f['type'] === 'success' ? 'check-circle' : ($f['type'] === 'error' ? 'alert' : 'info')) ?><div><?= e($f['message']) ?></div></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<div class="toast-zone" id="toasts"></div>
<?php $liveOn = support_live_enabled(); $hasChat = $liveOn && support_open_chat((int)$u['id']); ?>
<button type="button" class="help-fab<?= $hasChat ? ' has-chat' : '' ?>" data-help-open aria-label="Besoin d'aide ?" title="Besoin d'aide ?"><?= icon('info', 22) ?><span><?= $hasChat ? 'Conversation' : 'Aide' ?></span></button>
<section class="help-panel" data-help-panel hidden aria-label="Assistance" data-live="<?= $liveOn ? '1' : '0' ?>" data-has-chat="<?= $hasChat ? '1' : '0' ?>" data-operator="<?= e(support_contact()['editor']) ?>" data-autoopen="<?= ($r === 'support' && (input('chat') === '1')) ? '1' : '0' ?>">
  <header>
    <div><strong data-help-title>Assistance Centriva</strong><small data-help-sub>Réponses immédiates · équipe <?= e(support_contact()['editor']) ?> si besoin</small></div>
    <button type="button" class="btn btn-ghost btn-icon" data-help-close aria-label="Fermer"><?= icon('x', 18) ?></button>
  </header>
  <div class="help-log" data-help-log>
    <div class="msg bot">Bonjour <?= e($u['first_name']) ?> 👋 Posez votre question : je réponds tout de suite aux questions courantes, et je vous mets en relation avec un conseiller <?= e(support_contact()['editor']) ?> si besoin.</div>
    <div class="help-chips">
      <?php foreach (array_slice(array_filter(support_faq(), fn($f) => !$f[4] || is_admin()), 0, 4) as $f): ?><button type="button" data-help-ask="<?= e($f[0]) ?>"><?= e($f[0]) ?></button><?php endforeach; ?>
      <?php if (video_list(is_admin())): ?><a class="help-chip-link" href="<?= url('videos') ?>">🎬 Tutoriels vidéo</a><?php endif; ?>
      <?php if ($liveOn): ?><button type="button" data-help-live>👤 Parler à un conseiller</button><?php endif; ?>
    </div>
  </div>
  <form class="help-input" data-help-form>
    <?php if ($liveOn): ?><label class="btn btn-ghost btn-icon help-attach" title="Joindre une capture d'écran au conseiller" hidden data-help-attach><?= icon('camera', 18) ?><input type="file" accept="image/png,image/jpeg,image/webp" hidden></label><?php endif; ?>
    <input type="text" placeholder="Votre question…" autocomplete="off" maxlength="2000">
    <button class="btn btn-primary btn-icon" type="submit" aria-label="Envoyer"><?= icon('send', 18) ?></button>
  </form>
  <footer><?php if ($liveOn): ?><a href="#" data-help-live>Parler à un conseiller</a> · <?php endif; ?><a href="<?= url('support') ?>">Formulaire de contact</a><span data-help-end hidden> · <a href="#" data-help-close-chat>Terminer la conversation</a></span></footer>
</section>
<?php if ($wv = video_welcome_due($u)): ?>
<div class="welcome-video" data-welcome-video role="dialog" aria-modal="true" aria-labelledby="wv-title">
  <div class="welcome-box">
    <div class="welcome-head"><div><h2 id="wv-title">Bienvenue sur <?= e(app_name()) ?>, <?= e($u['first_name']) ?>&nbsp;!</h2><p class="muted">Découvrez en quelques minutes comment demander des articles, suivre vos demandes et réceptionner les livraisons.</p></div>
      <button type="button" class="btn btn-ghost btn-icon" data-welcome-close aria-label="Fermer"><?= icon('x', 20) ?></button></div>
    <video controls playsinline preload="metadata" src="<?= url('video/file', ['v' => $wv['uid']]) ?>"></video>
    <div class="welcome-foot">
      <label class="check mb-0"><input type="checkbox" data-welcome-off> <span>Ne plus afficher</span></label>
      <span class="spacer"></span>
      <a class="btn btn-ghost" href="<?= url('videos', ['v' => $wv['uid']]) ?>"><?= icon('play', 16) ?> Tous les tutoriels</a>
      <button type="button" class="btn btn-primary" data-welcome-close>Fermer</button>
    </div>
    <small class="muted">La vidéo reste disponible à tout moment dans le menu « Tutoriels vidéo ».</small>
  </div>
</div>
<?php endif; ?>
<script>window.APP = { csrf: <?= json_encode(csrf_token()) ?>, showPrices: <?= show_prices() ? 'true' : 'false' ?>, version: <?= json_encode(APP_VERSION) ?> };</script>
<script src="assets/js/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
