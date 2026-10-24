<?php
use App\Core\Database;
$unread = (int) Database::value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
$pendingReviews = App\Services\Reviews::pendingCount();
$attention = (int) Database::value("SELECT COUNT(*) FROM orders WHERE fulfillment_status IN ('error','draft','artwork') AND status IN ('paid','in_production')");
$success = flash('success');
$error = flash('error');
$nav = [
    'admin'              => ['Tableau de bord', '/admin', true],
    'admin/commandes'    => ['Commandes' . ($attention ? " <span class=\"pill\">$attention</span>" : ''), '/admin/commandes', false],
    'admin/produits'     => ['Produits', '/admin/produits', false],
    'admin/categories'   => ['Catégories', '/admin/categories', false],
    'admin/avis'         => ['Avis clients' . ($pendingReviews ? " <span class=\"pill\">$pendingReviews</span>" : ''), '/admin/avis', false],
    'admin/clients'      => ['Clients & newsletter', '/admin/clients', false],
    'admin/messages'     => ['Messages' . ($unread ? " <span class=\"pill\">$unread</span>" : ''), '/admin/messages', false],
    'admin/accueil'      => ['Page d\'accueil', '/admin/accueil', false],
    'admin/pages'        => ['Pages', '/admin/pages', false],
    'admin/reglages'     => ['Réglages', '/admin/reglages', false],
    'admin/integrations' => ['Intégrations', '/admin/integrations', false],
    'admin/mise-a-jour'  => ['Mise à jour', '/admin/mise-a-jour', false],
];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Administration') ?> — <?= e(setting('shop_name')) ?></title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<aside class="sidebar">
  <a class="sidebar-brand" href="<?= e(url('admin')) ?>"><img src="<?= e(url('assets/img/brand/logo-creme.svg')) ?>" alt="<?= e(setting('shop_name')) ?>" height="44"></a>
  <nav>
    <?php foreach ($nav as $path => [$label, $prefix, $exact]): ?>
      <a href="<?= e(url($path)) ?>" class="<?= ($exact ? request_path() === $prefix : is_active($prefix)) ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a href="<?= e(url('/')) ?>" target="_blank">Voir la boutique ↗</a>
    <span>Version <?= e((new App\Services\Updater())->currentVersion()) ?></span>
    <form method="post" action="<?= e(url('admin/deconnexion')) ?>"><?= csrf_field() ?><button class="link" type="submit">Déconnexion</button></form>
  </div>
</aside>
<main class="admin-main">
  <header class="admin-head"><h1><?= e($pageTitle ?? '') ?></h1></header>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?= $content ?>
</main>
<script>
document.querySelectorAll('[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
});
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () { navigator.clipboard.writeText(b.dataset.copy); b.textContent = 'Copié ✓'; });
});
</script>
</body>
</html>
